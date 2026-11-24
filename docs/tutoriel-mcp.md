# Piloter votre site FLEXIcontent avec un assistant IA (MCP)

Ce tutoriel explique comment connecter un assistant IA (OpenCode, Claude,
Cursor, VS Code, Gemini CLI, etc.) à votre site Joomla/FLEXIcontent. Une fois
connecté, l'assistant peut rechercher des contenus, créer ou modifier des items,
gérer les catégories, tags, types et champs, et importer des fichiers — toujours
avec les permissions du compte Joomla que vous aurez choisi.

Le serveur utilise le **Model Context Protocol (MCP)**, un standard ouvert :
aucun client n'est propriétaire du serveur. Les mêmes réglages fonctionnent donc
dans plusieurs outils d'IA.

> **Durée estimée : 15 minutes.** Aucune modification du cœur de Joomla ni de
> FLEXIcontent n'est nécessaire.

---

## 1. Comprendre ce que vous allez installer

Le serveur MCP est un petit programme Python qui sert de traducteur :

```
Assistant IA  ──MCP──>  Serveur MCP FLEXIcontent  ──JSON:API──>  Votre site Joomla
```

L'assistant ne parle pas directement à Joomla. Il appelle des outils comme
`flexi_search_items` ou `flexi_update_item`. Le serveur vérifie la configuration,
ajoute le token Joomla, appelle l'API puis renvoie un résultat lisible.

### Ce que l'assistant pourra faire

| Besoin | Outil utilisé |
|---|---|
| Chercher des items, filtrer par champ | `flexi_search_items` |
| Lire un item et ses champs personnalisés | `flexi_get_item` |
| Voir les catégories, types, champs et tags | `flexi_list_taxonomies`, `flexi_get_taxonomy` |
| Découvrir les ids valides avant d'écrire | `flexi_describe` |
| Créer ou modifier un contenu | `flexi_create_item`, `flexi_update_item` |
| Supprimer un contenu (confirmation obligatoire) | `flexi_delete_item` |
| Créer une catégorie, un type, un champ, un tag | `flexi_create_taxonomy`, `flexi_update_taxonomy` |
| Importer une image ou un document | `flexi_upload_file` |

Deux transports sont disponibles :

- **stdio** (par défaut) : le client IA lance le serveur localement. Idéal pour
  un poste de travail, sans port réseau à ouvrir.
- **HTTP Streamable** : le serveur tourne en permanence et répond sur
  `https://votre-domaine/mcp`. Utile pour plusieurs clients ou un usage distant.

---

## 2. Préparer Joomla

### 2.1 Activer les plugins nécessaires

Dans l'administration Joomla :

1. **Système → Gérer → Plugins**, vérifiez que ces plugins sont activés :
   - `Utilisateur - Joomla API Token` (*User – Joomla API Token*) ;
   - `Authentification API - Joomla Token` (*API Authentication – Joomla Token*) ;
   - le plugin **Web Services FLEXIcontent** fourni par l'extension.
2. Laissez **Authentification API - Basic** désactivé : il enverrait le mot de
   passe à chaque appel.
3. Dans **Utilisateur - Joomla API Token → Groupes autorisés**, gardez la liste
   la plus courte possible. Par défaut, seuls les Super Users peuvent avoir un
   token.

### 2.2 Créer un compte dédié

Créez de préférence un utilisateur réservé à l'automation, avec le minimum de
droits : par exemple création/modification dans une seule catégorie. Le token
agira **avec les permissions Joomla de cet utilisateur** : l'API n'ajoute aucun
droit supplémentaire.

Accordez-lui l'action **Web Services Login** (`core.login.api`) via
**Système → Configuration globale → Permissions** ou les permissions du groupe.

### 2.3 Générer le token API

1. Dans **Utilisateurs → Gérer**, ouvrez cet utilisateur.
2. Ouvrez l'onglet **Joomla API Token**.
3. Si le champ est vide, enregistrez une fois le profil puis rouvrez l'onglet.
4. Cliquez sur **Copier** et conservez le token dans un gestionnaire de secrets.
   Joomla ne pourra plus vous l'afficher ensuite.
5. Vérifiez que le token est bien **actif**.

Adresse de base de l'API FLEXIcontent :

```
https://exemple.com/api/index.php/v1/flexicontent
```

Remplacez `exemple.com` par votre domaine, sans slash final.

Test rapide depuis un terminal :

```sh
curl -H "Authorization: Bearer $FLEXI_API_TOKEN" \
  "https://exemple.com/api/index.php/v1/flexicontent/items?limit=1"
```

Vous devez recevoir du JSON. Une erreur `401` indique un token invalide ou
inactif ; une erreur `403` indique un problème de permissions.

---

## 3. Installer le serveur MCP

Le serveur nécessite Python 3.10 ou plus récent. Récupérez le dossier `mcp/`
fourni avec FLEXIcontent, puis :

```sh
cd mcp
python3 -m venv .venv
.venv/bin/python -m pip install -e .
```

La commande `flexicontent-mcp` est maintenant installée dans `.venv/bin/`.
Vérifiez :

```sh
.venv/bin/flexicontent-mcp --help
```

---

## 4. Configurer le serveur une seule fois

Toute la configuration sensible reste **côté serveur**. Les fichiers de
configuration des clients IA ne contiennent aucun token.

Créez le fichier de profil :

```sh
mkdir -p ~/.config/flexicontent-mcp
```

Collez-y ce contenu dans
`~/.config/flexicontent-mcp/config.toml` :

```toml
default_profile = "production"

[profiles.production]
base_url = "https://exemple.com/api/index.php/v1/flexicontent"
token_file = "token.txt"
read_only = true
```

`token.txt` est cherché **à côté du fichier `config.toml`**. Créez-le sans laisser
le token dans l'historique du shell :

```sh
read -rs -p "Token Joomla API : " FLEXI_TOKEN; echo
printf '%s' "$FLEXI_TOKEN" > ~/.config/flexicontent-mcp/token.txt
chmod 600 ~/.config/flexicontent-mcp/token.txt
unset FLEXI_TOKEN
```

Autres possibilités, selon votre gestionnaire de secrets :

```toml
token_env = "FLEXI_API_TOKEN"        # variable d'environnement
token_command = "security find-generic-password -s flexicontent -w"  # trousseau macOS
```

La priorité est toujours : **options CLI > variables d'environnement > profil
TOML > valeurs par défaut**.

### Pourquoi `read_only = true` au début ?

Avec ce réglage, tous les outils d'écriture sont désactivés. Commencez en
lecture seule, validez que l'assistant voit bien vos contenus, puis passez à
`read_only = false` (ou lancez avec `--no-read-only`) quand vous voulez autoriser
les modifications. Les suppressions demandent de toute façon `confirm: true`.

Test du profil :

```sh
.venv/bin/flexicontent-mcp --profile production
```

Le serveur attend des instructions sur l'entrée standard : c'est normal. Dans un
client IA, il démarrera automatiquement.

---

## 5. Brancher votre client IA

### OpenCode

Dans `opencode.json` :

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": [
          "/chemin/vers/mcp/.venv/bin/flexicontent-mcp",
          "--profile", "production"
        ]
      }
    }
  }
}
```

Vérification :

```sh
opencode mcp list
```

Vous devez voir `✓ flexicontent  connected`.

### Claude Desktop

Dans `claude_desktop_config.json` :

```json
{
  "mcpServers": {
    "flexicontent": {
      "command": "/chemin/vers/mcp/.venv/bin/flexicontent-mcp",
      "args": ["--profile", "production"]
    }
  }
}
```

Redémarrez Claude Desktop après modification.

### Claude Code

```sh
claude mcp add --transport stdio flexicontent -- \
  /chemin/vers/mcp/.venv/bin/flexicontent-mcp --profile production
```

Puis `claude mcp list` et `/mcp` dans la session.

### Cursor

Dans `~/.cursor/mcp.json` ou `.cursor/mcp.json` du projet :

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/chemin/vers/mcp/.venv/bin/flexicontent-mcp",
      "args": ["--profile", "production"]
    }
  }
}
```

### VS Code / GitHub Copilot

Dans `.mcp.json` à la racine du workspace :

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/chemin/vers/mcp/.venv/bin/flexicontent-mcp",
      "args": ["--profile", "production"],
      "tools": ["*"]
    }
  }
}
```

Dans tous les cas, remplacez `/chemin/vers/mcp/` par le chemin réel du dossier
`mcp/` sur votre machine. Le token ne figure jamais dans ces fichiers.

---

## 6. Premiers échanges avec l'assistant

Vérifiez d'abord la connexion en demandant :

> « Utilise flexi_describe pour me dire quels types et quelles catégories sont
> disponibles sur le site. »

Puis des demandes concrètes, par exemple :

> « Cherche les réglementations publiées en 2025 et donne-moi leur titre, leur
> id et leur catégorie. »

> « Montre-moi l'item 151 avec tous ses champs personnalisés. »

> « Crée un brouillon intitulé "Arrêté test" dans la catégorie Réglementations,
> mais ne publie pas. »

> « Modifie l'item 151 : change son titre et ajoute `type_de_lois =
> environnement`. Montre-moi l'avant et l'après. »

> « Importe ce PDF et attache-le à l'item 151. »

Avant chaque écriture, l'assistant doit appeler `flexi_describe` pour connaître
les ids valides. Cela évite les erreurs classiques `422` sur les types, champs ou
catégories.

### Suppression

> « Supprime l'item 151. »

Le serveur refusera tant que la confirmation n'est pas explicite. Demandez
alors :

> « Confirme la suppression de l'item 151. »

---

## 7. Utiliser le serveur en HTTP partagé

Si plusieurs personnes ou plusieurs machines doivent se connecter, lancez le
serveur en mode HTTP :

```sh
.venv/bin/flexicontent-mcp \
  --profile production \
  --transport streamable-http \
  --host 127.0.0.1 \
  --port 8000 \
  --allowed-host mcp.exemple.com:*
```

L'endpoint est :

```
http://127.0.0.1:8000/mcp
```

Placez toujours un **reverse proxy authentifié en HTTPS** devant ce service :
le token Joomla reste côté serveur, mais l'accès au serveur MCP doit être
protégé (Basic, Bearer ou OAuth sur le proxy). Le paramètre `--allowed-host` est
nécessaire derrière un proxy, sinon la protection anti-DNS-rebinding du SDK rejette
les requêtes.

Côté client OpenCode :

```jsonc
{
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "remote",
        "url": "https://mcp.exemple.com/mcp",
        "oauth": false
      }
    }
  }
}
```

`oauth: false` est important : l'authentification auprès de Joomla est déjà
gérée par le serveur MCP.

---

## 8. Résoudre les problèmes courants

| Symptôme | Cause probable | Solution |
|---|---|---|
| `✓ flexicontent connected` absent | Chemin du venv incorrect ou profil introuvable | Vérifiez `command` et `--profile`, puis relancez le client |
| Erreur `401` | Token invalide, désactivé ou régénéré | Copiez le nouveau token Joomla API et mettez à jour `token.txt` |
| Erreur `403` | ACL insuffisante | Vérifiez les droits Joomla du compte et `core.login.api` |
| Erreur `404` | URL de base ou id incorrect | L'URL doit se terminer par `/v1/flexicontent`, sans slash final |
| Erreur `422` | Champ ou type invalide | Appelez `flexi_describe` avant d'écrire |
| `Writes are disabled` | Profil en lecture seule | Retirez `read_only = true` ou utilisez `--no-read-only` |
| Erreur TLS sur un site de dev | Certificat auto-signé (DDEV) | Ajoutez `--no-verify-ssl` **en développement uniquement** |
| Requête HTTP rejetée (`Invalid Host header`) | Proxy derrière localhost | Ajoutez `--allowed-host nom-de-domaine:*` |
| Le client ne voit aucun outil | Connexion stdio mal configurée | Utilisez le chemin absolu vers `.venv/bin/flexicontent-mcp` |

Pour un diagnostic plus fin, lancez le serveur avec `--log-level DEBUG` : les
journaux sont écrits sur la sortie d'erreur, donc ils ne polluent pas la
conversation MCP.

---

## 9. Bonnes pratiques à retenir

- Un compte Joomla dédié, avec le minimum de permissions.
- Un token stocké dans un fichier `0600`, une variable d'environnement ou un
  trousseau — jamais dans un fichier de configuration de client IA.
- Commencer en lecture seule, puis activer l'écriture.
- Toujours passer par `flexi_describe` avant de créer ou modifier.
- HTTPS en production, Basic Auth désactivé, token révoqué en cas de fuite.
- En HTTP, ne jamais exposer directement le port du serveur sans proxy
  authentifié.

Le résultat : votre assistant IA devient un opérateur FLEXIcontent qui respecte
les mêmes règles que vos utilisateurs Joomla, tout en restant remplaçable par
n'importe quel autre client compatible MCP.
