# Brancher le serveur MCP FLEXIcontent sur différents clients

Le serveur parle le **Model Context Protocol** standard. Tous les clients qui
supportent MCP peuvent donc l'utiliser : rien n'est propre à OpenCode.

Deux transports sont disponibles :

| Transport | Quand l'utiliser | Option serveur |
|---|---|---|
| **stdio** (défaut) | Client local qui lance le processus lui-même | défaut, `--transport stdio` |
| **Streamable HTTP** | Client distant, web, ou processus partagé | `--transport streamable-http`, endpoint `/mcp` |

## Prérequis commun

Le serveur n'a besoin que de deux réglages : l'URL de l'API et un token Joomla.
Trois façons de les fournir, par ordre de priorité :

1. options CLI (`--base-url`, `--token-env`, `--token-file`, `--token-command`) ;
2. variables d'environnement (`FLEXI_BASE_URL`, `FLEXI_API_TOKEN`, …) ;
3. profil dans `~/.config/flexicontent-mcp/config.toml`.

Exemple de profil :

```toml
default_profile = "dev"

[profiles.dev]
base_url = "https://flexi-dev.ddev.site/api/index.php/v1/flexicontent"
token_env = "FLEXI_API_TOKEN"
verify_ssl = false

[profiles.prod]
base_url = "https://example.com/api/index.php/v1/flexicontent"
token_file = "prod.token"      # relatif au fichier de configuration, 0600
read_only = true
```

Le lancement devient alors simplement `flexicontent-mcp --profile dev`.
Voir [`mcp/README.md`](../mcp/README.md) pour la liste complète des options.

> **Ne jamais coller de token dans un fichier de configuration de client.**
> Utiliser une référence d'environnement (`{env:NAME}` pour OpenCode,
> `${env:NAME}` pour Cursor, `${NAME}` pour VS Code), un fichier de token en
> `0600`, ou `--token-command`. La commande doit afficher uniquement le token
> sur stdout (ex. trousseau macOS ci-dessous) ; elle est exécutée comme argv,
> sans shell.

## OpenCode (V2)

Local, avec référence d'environnement :

```jsonc title="opencode.json"
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": ["flexicontent-mcp", "--profile", "dev"],
        "environment": {
          "FLEXI_API_TOKEN": "{env:FLEXI_API_TOKEN}"
        }
      }
    }
  }
}
```

Vérification : `opencode mcp list` doit afficher `✓ flexicontent  connected`.

Distant (serveur HTTP) :

```jsonc
{
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "remote",
        "url": "https://mcp.example.com/mcp",
        "oauth": false
      }
    }
  }
}
```

`oauth: false` est indispensable : le serveur s'authentifie auprès de Joomla
côté serveur, pas auprès du client.

## Claude Desktop

Fichier `~/Library/Application Support/Claude/claude_desktop_config.json`
(macOS), `%APPDATA%\Claude\claude_desktop_config.json` (Windows) ou
`~/.config/Claude/claude_desktop_config.json` (Linux) :

```json
{
  "mcpServers": {
    "flexicontent": {
      "command": "/chemin/du/venv/bin/flexicontent-mcp",
      "args": [
        "--profile", "dev",
        "--token-command", "security find-generic-password -s flexicontent-dev -w"
      ]
    }
  }
}
```

Le trousseau macOS stocke le token une fois :

```sh
security add-generic-password -s flexicontent-dev -a "$USER" -w
```

Sans trousseau, remplacer par `"--token-file", "/chemin/secret.token"` (fichier
en `0600`) ou par `"--token-env", "FLEXI_API_TOKEN"` avec `env` fourni par le
client. Redémarrer Claude Desktop après modification. Les journaux MCP se trouvent dans
`~/Library/Logs/Claude` (macOS), `%APPDATA%\Claude\logs` (Windows) ou
`~/.config/Claude/logs` (Linux).

## Claude Code

En ligne de commande :

```sh
claude mcp add --transport stdio flexicontent -- \
  /chemin/du/venv/bin/flexicontent-mcp --profile dev \
  --token-command "security find-generic-password -s flexicontent-dev -w"
```

Ou dans `.mcp.json` à la racine du projet (format partageable) :

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/chemin/du/venv/bin/flexicontent-mcp",
      "args": [
        "--profile", "dev",
        "--token-command", "security find-generic-password -s flexicontent-dev -w"
      ]
    }
  }
}
```

`${FLEXI_API_TOKEN}` dans `env` fonctionne aussi, mais `--token-command` évite
de faire dépendre la configuration du trousseau du client.

Vérification : `claude mcp list` puis `/mcp` dans la session.

## Cursor

Fichier `~/.cursor/mcp.json` (global) ou `<workspace>/.cursor/mcp.json` :

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/chemin/du/venv/bin/flexicontent-mcp",
      "args": [
        "--profile", "dev",
        "--token-command", "security find-generic-password -s flexicontent-dev -w"
      ]
    }
  }
}
```

Cursor résout `${env:FLEXI_API_TOKEN}` dans `env` pour un serveur stdio ; le CLI
Cursor utilise plutôt `${FLEXI_API_TOKEN}`. `--token-command` reste le plus
portable entre les deux.

Format portable dans `.mcp.json` à la racine du workspace :

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/chemin/du/venv/bin/flexicontent-mcp",
      "args": [
        "--profile", "dev",
        "--token-command", "security find-generic-password -s flexicontent-dev -w"
      ],
      "tools": ["*"]
    }
  }
}
```

VS Code accepte aussi `"env": { "FLEXI_API_TOKEN": "${FLEXI_API_TOKEN}" }`.
Pour un serveur HTTP, remplacer l'entrée par :

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "http",
      "url": "http://127.0.0.1:8000/mcp",
      "tools": ["*"]
    }
  }
}
```

Les anciens fichiers `.vscode/mcp.json` utilisent un format différent
(`servers` au lieu de `mcpServers`) ; préférer `.mcp.json` pour les nouvelles
configurations.

## Windsurf

Fichier `~/.codeium/windsurf/mcp_config.json` :

```json
{
  "mcpServers": {
    "flexicontent": {
      "command": "/chemin/du/venv/bin/flexicontent-mcp",
      "args": [
        "--profile", "dev",
        "--token-file", "/chemin/secret.token"
      ]
    }
  }
}
```

## Mode HTTP partagé

Démarrage :

```sh
flexicontent-mcp --profile prod \
  --transport streamable-http \
  --host 127.0.0.1 --port 8000 \
  --allowed-host mcp.example.com:*
```

Points importants :

- le token Joomla reste dans le profil du serveur, jamais chez le client ;
- écouter sur `127.0.0.1` et passer par un reverse proxy authentifié
  (HTTPS + Basic/Bearer/OAuth) avant toute exposition ;
- `--allowed-host` / `--allowed-origin` sont nécessaires derrière un proxy,
  sinon la protection anti-DNS-rebinding du SDK rejette les requêtes ;
- les clients HTTP se connectent à `https://mcp.example.com/mcp`.

## Vérification rapide (stdio)

```sh
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke","version":"1"}}}' \
  | FLEXI_BASE_URL=https://flexi-dev.ddev.site/api/index.php/v1/flexicontent \
    FLEXI_API_TOKEN=... \
    /chemin/du/venv/bin/flexicontent-mcp
```

La réponse doit contenir `serverInfo` avec le nom `flexicontent` et une liste
de 12 outils via `tools/list`.
