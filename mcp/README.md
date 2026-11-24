# Serveur MCP FLEXIcontent

Serveur MCP local (stdio) qui expose l’API JSON:API FLEXIcontent de Joomla.
Il ne modifie pas le cœur FLEXIcontent : il traduit les outils MCP en appels
`Bearer` vers `/api/index.php/v1/flexicontent`.

## Installation

```sh
cd repos/flexicontent-cck/mcp
python3 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
```

Pour un DDEV avec certificat auto-signé, garder `FLEXI_VERIFY_SSL=0` en
développement. En production, laisser la valeur par défaut (`1`).

## Variables d’environnement

| Variable | Requise | Défaut | Rôle |
|---|---|---|---|
| `FLEXI_BASE_URL` | oui | — | URL de base de l’API, sans slash final |
| `FLEXI_API_TOKEN` | oui | — | Token Joomla API (Bearer) |
| `FLEXI_READONLY` | non | `0` | `1` désactive `create`/`update`/`delete` |
| `FLEXI_VERIFY_SSL` | non | `1` | `0` accepte le certificat DDEV auto-signé |
| `FLEXI_TIMEOUT` | non | `30` | Timeout HTTP en secondes |
| `FLEXI_MAX_LIMIT` | non | `100` | Plafond de `limit` pour toutes les listes |

## Configuration OpenCode

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": [
          "/home/yannick/www/flexi-dev/repos/flexicontent-cck/mcp/.venv/bin/python",
          "/home/yannick/www/flexi-dev/repos/flexicontent-cck/mcp/run.py"
        ],
        "environment": {
          "FLEXI_BASE_URL": "https://flexi-dev.ddev.site/api/index.php/v1/flexicontent",
          "FLEXI_API_TOKEN": "{env:FLEXI_API_TOKEN}",
          "FLEXI_VERIFY_SSL": "0"
        }
      }
    }
  }
}
```

Pour le poste de dev DDEV, `run-dev.sh` lit automatiquement `admin_token`
depuis `bruno/flexicontent-api/environments/dev.bru` : aucun secret dans
`opencode.json`. La configuration réellement utilisée est donc :

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": ["/home/yannick/www/flexi-dev/repos/flexicontent-cck/mcp/run-dev.sh"],
        "environment": {
          "FLEXI_BASE_URL": "https://flexi-dev.ddev.site/api/index.php/v1/flexicontent",
          "FLEXI_VERIFY_SSL": "0"
        }
      }
    }
  }
}
```

Vérification :

```sh
opencode mcp list
```

## Outils

| Outil | Accès | Rôle |
|---|---|---|
| `flexi_search_items` | lecture | Recherche d’items avec filtres JSON:API |
| `flexi_get_item` | lecture | Détail d’un item + `flexi_fields` |
| `flexi_list_taxonomies` | lecture | Catégories, tags, types, champs |
| `flexi_get_taxonomy` | lecture | Détail d’un type, champ, catégorie ou tag |
| `flexi_describe` | lecture | Ids/noms et schémas `kind="taxonomies"` |
| `flexi_create_item` | écriture | `POST /items` |
| `flexi_update_item` | écriture | `PATCH /items/{id}` partiel |
| `flexi_delete_item` | écriture | `DELETE /items/{id}`, exige `confirm=true` |
| `flexi_upload_file` | écriture | Upload image/fichier (`POST /media`) |
| `flexi_create_taxonomy` | écriture | `POST /{types,fields,categories,tags}` |
| `flexi_update_taxonomy` | écriture | `PATCH /{resource}/{id}` partiel |
| `flexi_delete_taxonomy` | écriture | `DELETE /{resource}/{id}`, exige `confirm=true` |

Les écritures suivent l’ACL du token Joomla. `FLEXI_READONLY=1` permet de
brancher le même serveur sur un profil OpenCode en lecture seule.

Exemples :

```jsonc
// flexi_describe(kind="taxonomies") donne les attributs autorisés par ressource.
flexi_create_taxonomy("types", {"name": "Reglementation", "alias": "reglementation"})
flexi_create_taxonomy("fields", {"label": "Numéro", "name": "numero", "field_type": "text"})
flexi_create_taxonomy("categories", {"title": "Actualités", "parent_id": 1})
flexi_create_taxonomy("tags", {"name": "Urgent"})

flexi_update_taxonomy("tags", 42, {"published": 0})
flexi_delete_taxonomy("tags", 42, true)

// Upload : content_path ou content_base64, avec field_id ou field_name.
flexi_upload_file(content_path="/tmp/arbre.png", field_name="image_principale")
flexi_upload_file(filename="arret.pdf", content_base64="...", field_name="fichier_pdf")

// La valeur renvoyée s'attache directement à un item :
flexi_create_item("Arrêté", 24, 11, fields={"image_principale": [{"originalname": "arbre.png"}]})
```

Les ids de type, champ, catégorie et tag renvoyés par ces outils sont les ids
FLEXIcontent à réutiliser dans les écritures d’items.

Les types créés via l’API reçoivent les valeurs par défaut de leur formulaire
XML, comme dans l’administration (notamment `attribs.ilayout = "grid"`). Pour
choisir un autre template : `flexi_create_taxonomy("types", {"name": "...",
"attribs": {"ilayout": "article"}})`.

Note : la suppression d’un tag FLEXIcontent laisse le tag Joomla associé par
défaut (option legacy `remove_cascade_jtags` désactivée). Le champ `jtag_id`
renvoyé en lecture permet de le repérer et de le supprimer via l’API Joomla
`/v1/tags` si nécessaire.

## Uploads

L’endpoint `POST /v1/flexicontent/media` respecte le mode de stockage du champ
image cible :

- **Mode BDD** (`image_source=0`) : le fichier va dans `file_path` (sécurisé) ou
  `media_path` (public) selon `target_dir`, et une entrée `#__flexicontent_files`
  est créée. Le paramètre `secure` de l’outil permet de forcer le choix.
- **Mode dossier** (`image_source>=1`) : `item_id` est obligatoire. L’original
  est écrit dans `<dir>/item_<item_id>_field_<field_id>/original/`, exactement
  là où `image.php` le cherche. Aucune entrée `#__flexicontent_files` n’est
  créée et `secure` vaut `0`. Les miniatures sont générées par FLEXIcontent au
  premier affichage, dans le dossier parent. Le répertoire `original` est
  protégé par un `.htaccess` quand `protect_original=1`.
- Les modes Joomla media manager / intro-full (`image_source<0`) sont refusés.

Les extensions et la taille sont validées avec la configuration du composant
et, si `field_id`/`field_name` est fourni, avec celle du champ cible. Les images
sont vérifiées avec `getimagesizefromstring()` puis décodées avec
`imagecreatefromstring()` : un PNG tronqué ou corrompu est rejeté au lieu de
produire une valeur inutilisable.

Le champ `field_value` de la réponse est directement utilisable dans `fields` :
une liste d’objets `originalname` pour un champ `image`, l’id du fichier pour
un champ `file`/`media`. La réponse indique aussi `storage`
(`flexicontent_files` ou `field_folder`).

```python
flexi_upload_file(content_path="/tmp/photo.png", field_name="image_principale", item_id=151)
```

Le serveur accepte soit du contenu base64, soit un chemin local lisible par le
processus MCP. Dans les deux cas, un seul doit être fourni. Les écritures sont
également bloquées par `FLEXI_READONLY=1`.

## Tests

```sh
.venv/bin/python -m unittest discover -s tests -v
```
