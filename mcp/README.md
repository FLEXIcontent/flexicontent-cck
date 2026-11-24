# Serveur MCP FLEXIcontent

Serveur MCP qui expose l’API JSON:API FLEXIcontent de Joomla (transport stdio
par défaut, ou HTTP Streamable). Il ne modifie pas le cœur FLEXIcontent : il
traduit les outils MCP en appels `Bearer` vers `/api/index.php/v1/flexicontent`.

## Installation

```sh
cd repos/flexicontent-cck/mcp
python3 -m venv .venv
.venv/bin/python -m pip install -e .
```

`-e .` installe la commande `flexicontent-mcp` dans le venv. Pour une
installation non éditable : `.venv/bin/python -m pip install .`.

## Configuration

Priorité : **options CLI > variables d’environnement > profil TOML > défauts**.

Profil dans `$XDG_CONFIG_HOME/flexicontent-mcp/config.toml`
(`~/.config/flexicontent-mcp/config.toml` par défaut) :

```toml
default_profile = "dev"

[profiles.dev]
base_url = "https://flexi-dev.ddev.site/api/index.php/v1/flexicontent"
token_env = "FLEXI_API_TOKEN"
verify_ssl = false

[profiles.prod]
base_url = "https://example.com/api/index.php/v1/flexicontent"
token_file = "prod.token"     # relatif au fichier de configuration, 0600
read_only = true
```

Le token peut venir de `token_env`, `token_file` ou `token_command` (commande
qui l’affiche sur stdout, analysée comme argv, sans shell). Le fichier de
configuration peut être changé avec `--config` ou `FLEXI_CONFIG`.

```sh
flexicontent-mcp --profile dev
flexicontent-mcp --base-url https://... --token-env MY_TOKEN --no-verify-ssl
flexicontent-mcp --profile prod --read-only
flexicontent-mcp --help
```

### Variables d’environnement

| Variable | Requise | Défaut | Rôle |
|---|---|---|---|
| `FLEXI_BASE_URL` | oui | — | URL de base de l’API, sans slash final |
| `FLEXI_API_TOKEN` | oui* | — | Token Joomla API (Bearer) |
| `FLEXI_PROFILE` | non | — | Profil à utiliser |
| `FLEXI_CONFIG` | non | — | Chemin du fichier TOML |
| `FLEXI_TOKEN_ENV` / `FLEXI_TOKEN_FILE` / `FLEXI_TOKEN_COMMAND` | non | — | Source du token |
| `FLEXI_READONLY` | non | `0` | `1` désactive `create`/`update`/`delete` |
| `FLEXI_VERIFY_SSL` | non | `1` | `0` accepte le certificat DDEV auto-signé |
| `FLEXI_TIMEOUT` | non | `30` | Timeout HTTP en secondes |
| `FLEXI_MAX_LIMIT` | non | `100` | Plafond de `limit` pour toutes les listes |
| `FLEXI_TRANSPORT` | non | `stdio` | `stdio`, `sse` ou `streamable-http` |
| `FLEXI_HOST` / `FLEXI_PORT` | non | `127.0.0.1` / `8000` | Écoute HTTP |
| `FLEXI_ALLOWED_HOSTS` / `FLEXI_ALLOWED_ORIGINS` | non | — | Listes séparées par des virgules (HTTP) |

\* Ou une autre source de token (`token_env`, `token_file`, `token_command`).

## Transports

stdio par défaut. Pour un client distant ou web :

```sh
flexicontent-mcp --profile prod --transport streamable-http --port 8000 \
  --allowed-host mcp.example.com:*
```

Endpoint : `http://127.0.0.1:8000/mcp`. Écouter sur `127.0.0.1` et placer un
reverse proxy authentifié devant avant exposition. Le token Joomla reste côté
serveur. Voir [`docs/mcp-clients.md`](../docs/mcp-clients.md) pour OpenCode,
Claude Desktop, Claude Code, Cursor, VS Code/Copilot et Windsurf. Un tutoriel
prêt à publier est aussi disponible dans
[`docs/tutoriel-mcp.md`](../docs/tutoriel-mcp.md).

## Configuration OpenCode

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": ["flexicontent-mcp", "--profile", "prod"],
        "environment": {
          "FLEXI_API_TOKEN": "{env:FLEXI_API_TOKEN}"
        }
      }
    }
  }
}
```

Sur le poste de dev DDEV, `run-dev.sh` lit automatiquement `admin_token` depuis
`bruno/flexicontent-api/environments/dev.bru` et utilise `config.dev.toml` :
aucun secret dans `opencode.json`. Configuration réellement utilisée :

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": ["/home/yannick/www/flexi-dev/repos/flexicontent-cck/mcp/run-dev.sh"]
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
| `flexi_update_item` | écriture | `PATCH /items/{id}` partiel, y compris `catid` |
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

// Déplacer un item dans une autre catégorie principale :
flexi_update_item(151, catid=27)
```

Exemple de champ `select` filtrable assigné à un type, puis ajouté aux filtres
d'une catégorie :

```jsonc
flexi_create_taxonomy("fields", {
  "label": "type de lois",
  "name": "type_de_lois",
  "field_type": "select",
  "published": 1,
  "isfilter": 1,
  "isadvfilter": 1,
  "tid": [11],
  "attribs": {
    "field_elements": "environnement::environnement%%\r\néconomie::économie%%\r\nsport::sport%%",
    "text_or_value": "1",
    "display_label_filter": "2"
  }
})

flexi_update_taxonomy("categories", 34, {
  "params": { "use_filters": 1, "filters": "136,142" }
})
```

Les ids de type, champ, catégorie et tag renvoyés par ces outils sont les ids
FLEXIcontent à réutiliser dans les écritures d’items.

Fixture `Réglementations` de la base de dev : type **11**, catégorie **34**,
champs **135** (`image_principale`), **136** (`date_publication_reglementation`),
**137** (`lien_site_officiel`) et **142** (`type_de_lois`), items **151-155**.
Répartition de `type_de_lois` : **151/154** = `environnement`, **152/155** =
`économie`, **153** = `sport`. Le champ 142 est filtrable et référencé dans
`params.filters` de la catégorie 34 avec le champ 136.

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
