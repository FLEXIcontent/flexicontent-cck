# Control your FLEXIcontent site with an AI assistant (MCP)

This tutorial explains how to connect an AI assistant (OpenCode, Claude, Cursor,
VS Code, Gemini CLI, etc.) to your Joomla/FLEXIcontent site. Once connected, the
assistant can search content, create or update items, manage categories, tags,
types and fields, and import files — always with the permissions of the Joomla
account you choose.

The server uses the **Model Context Protocol (MCP)**, an open standard: no
client owns the server. The same setup therefore works across several AI tools.

> **Estimated time: 15 minutes.** No changes to the Joomla or FLEXIcontent core
> are required.

---

## 1. Understand what you are installing

The MCP server is a small Python program that acts as a translator:

```
AI assistant  ──MCP──>  FLEXIcontent MCP server  ──JSON:API──>  Your Joomla site
```

The assistant does not talk to Joomla directly. It calls tools such as
`flexi_search_items` or `flexi_update_item`. The server checks the configuration,
adds the Joomla token, calls the API, then returns a readable result.

### What the assistant will be able to do

| Need | Tool |
|---|---|
| Search items, filter by custom field | `flexi_search_items` |
| Read an item and its custom fields | `flexi_get_item` |
| List categories, types, fields and tags | `flexi_list_taxonomies`, `flexi_get_taxonomy` |
| Discover valid ids before writing | `flexi_describe` |
| Create or update content | `flexi_create_item`, `flexi_update_item` |
| Delete content (confirmation required) | `flexi_delete_item` |
| Create a category, type, field or tag | `flexi_create_taxonomy`, `flexi_update_taxonomy` |
| Import an image or document | `flexi_upload_file` |

Two transports are available:

- **stdio** (default): the AI client starts the server locally. Ideal for a
  workstation, with no network port to open.
- **Streamable HTTP**: the server runs continuously and answers on
  `https://your-domain/mcp`. Useful for multiple clients or remote use.

---

## 2. Prepare Joomla

### 2.1 Enable the required plugins

In the Joomla administrator area:

1. Go to **System → Manage → Plugins** and make sure these plugins are enabled:
   - `User – Joomla API Token`;
   - `API Authentication – Joomla Token`;
   - the **FLEXIcontent Web Services** plugin shipped with the extension.
2. Leave **API Authentication – Basic** disabled: it would send the password on
   every call.
3. In **User – Joomla API Token → Allowed User Groups**, keep the list as short
   as possible. By default, only Super Users may have a token.

### 2.2 Create a dedicated account

Create a user reserved for automation, with the minimum rights: for example,
create/edit in a single category only. The token will act **with that Joomla
user's permissions**: the API grants no extra rights.

Grant that user the **Web Services Login** action (`core.login.api`) through
**System → Global Configuration → Permissions**, or via the group permissions.

### 2.3 Generate the API token

1. Go to **Users → Manage** and open that user.
2. Open the **Joomla API Token** tab.
3. If the field is empty, save the profile once, then reopen the tab.
4. Click **Copy** and store the token in a secret manager. Joomla will not be
   able to show it again.
5. Make sure the token is **active**.

The FLEXIcontent API base URL is:

```
https://example.com/api/index.php/v1/flexicontent
```

Replace `example.com` with your domain, with no trailing slash.

Quick test from a terminal:

```sh
curl -H "Authorization: Bearer $FLEXI_API_TOKEN" \
  "https://example.com/api/index.php/v1/flexicontent/items?limit=1"
```

You should receive JSON. A `401` error means an invalid or inactive token; a
`403` error means a permissions problem.

---

## 3. Install the MCP server

The server requires Python 3.10 or newer. Get the `mcp/` folder shipped with
FLEXIcontent, then run:

```sh
cd mcp
python3 -m venv .venv
.venv/bin/python -m pip install -e .
```

The `flexicontent-mcp` command is now installed in `.venv/bin/`.
Check it:

```sh
.venv/bin/flexicontent-mcp --help
```

---

## 4. Configure the server once

All sensitive configuration stays **on the server side**. AI client config files
never contain a token.

Create the profile file:

```sh
mkdir -p ~/.config/flexicontent-mcp
```

Paste this into `~/.config/flexicontent-mcp/config.toml`:

```toml
default_profile = "production"

[profiles.production]
base_url = "https://example.com/api/index.php/v1/flexicontent"
token_file = "token.txt"
read_only = true
```

`token.txt` is looked up **next to `config.toml`**. Create it without leaving the
token in your shell history:

```sh
read -rs -p "Joomla API token: " FLEXI_TOKEN; echo
printf '%s' "$FLEXI_TOKEN" > ~/.config/flexicontent-mcp/token.txt
chmod 600 ~/.config/flexicontent-mcp/token.txt
unset FLEXI_TOKEN
```

Other options, depending on your secret manager:

```toml
token_env = "FLEXI_API_TOKEN"        # environment variable
token_command = "security find-generic-password -s flexicontent -w"  # macOS keychain
```

Priority is always: **CLI options > environment variables > TOML profile >
built-in defaults**.

### Why start with `read_only = true`?

With this setting, all write tools are disabled. Start in read-only mode, check
that the assistant can see your content, then set `read_only = false` (or pass
`--no-read-only`) when you want to allow changes. Deletions still require
`confirm: true`.

Test the profile:

```sh
.venv/bin/flexicontent-mcp --profile production
```

The server waits for instructions on standard input: that is expected. In an AI
client, it will start automatically.

---

## 5. Connect your AI client

### OpenCode

In `opencode.json`:

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "flexicontent": {
        "type": "local",
        "command": [
          "/path/to/mcp/.venv/bin/flexicontent-mcp",
          "--profile", "production"
        ]
      }
    }
  }
}
```

Check with:

```sh
opencode mcp list
```

You should see `✓ flexicontent  connected`.

### Claude Desktop

In `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "flexicontent": {
      "command": "/path/to/mcp/.venv/bin/flexicontent-mcp",
      "args": ["--profile", "production"]
    }
  }
}
```

Restart Claude Desktop after editing the file.

### Claude Code

```sh
claude mcp add --transport stdio flexicontent -- \
  /path/to/mcp/.venv/bin/flexicontent-mcp --profile production
```

Then run `claude mcp list` and `/mcp` in the session.

### Cursor

In `~/.cursor/mcp.json` or the project's `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/path/to/mcp/.venv/bin/flexicontent-mcp",
      "args": ["--profile", "production"]
    }
  }
}
```

### VS Code / GitHub Copilot

In `.mcp.json` at the root of the workspace:

```json
{
  "mcpServers": {
    "flexicontent": {
      "type": "stdio",
      "command": "/path/to/mcp/.venv/bin/flexicontent-mcp",
      "args": ["--profile", "production"],
      "tools": ["*"]
    }
  }
}
```

In every case, replace `/path/to/mcp/` with the real path to the `mcp/` folder on
your machine. The token never appears in these files.

---

## 6. First conversations with the assistant

Check the connection first by asking:

> "Use `flexi_describe` to tell me which types and categories are available on
> the site."

Then make concrete requests, for example:

> "Find regulations published in 2025 and give me their title, id and category."

> "Show me item 151 with all its custom fields."

> "Create a draft titled 'Test decree' in the Regulations category, but do not
> publish it."

> "Update item 151: change its title and set `type_de_lois` to `environnement`.
> Show me the before and after."

> "Import this PDF and attach it to item 151."

Before any write, the assistant should call `flexi_describe` to learn the valid
type, field and category ids. This avoids the common `422` errors.

### Deletion

> "Delete item 151."

The server will refuse until confirmation is explicit. Then ask:

> "Confirm deletion of item 151."

---

## 7. Using the server over shared HTTP

If several people or machines need to connect, run the server in HTTP mode:

```sh
.venv/bin/flexicontent-mcp \
  --profile production \
  --transport streamable-http \
  --host 127.0.0.1 \
  --port 8000 \
  --allowed-host mcp.example.com:*
```

The endpoint is:

```
http://127.0.0.1:8000/mcp
```

Always put an **authenticated HTTPS reverse proxy** in front of this service:
the Joomla token stays on the server, but access to the MCP server must be
protected (Basic, Bearer or OAuth at the proxy). `--allowed-host` is required
behind a proxy; otherwise the SDK's DNS-rebinding protection rejects requests.

On the OpenCode side:

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

`oauth: false` matters: authentication with Joomla is already handled by the MCP
server.

---

## 8. Common problems

| Symptom | Likely cause | Fix |
|---|---|---|
| `✓ flexicontent connected` missing | Wrong venv path or profile not found | Check `command` and `--profile`, then restart the client |
| `401` error | Invalid, disabled or regenerated token | Copy the new Joomla API token and update `token.txt` |
| `403` error | Insufficient ACL | Check the Joomla account rights and `core.login.api` |
| `404` error | Wrong base URL or id | The URL must end in `/v1/flexicontent`, with no trailing slash |
| `422` error | Invalid field or type | Call `flexi_describe` before writing |
| `Writes are disabled` | Profile is read-only | Remove `read_only = true` or pass `--no-read-only` |
| TLS error on a dev site | Self-signed certificate (DDEV) | Add `--no-verify-ssl` **in development only** |
| HTTP request rejected (`Invalid Host header`) | Proxy in front of localhost | Add `--allowed-host your-domain:*` |
| Client sees no tools | Bad stdio configuration | Use the absolute path to `.venv/bin/flexicontent-mcp` |

For finer diagnostics, start the server with `--log-level DEBUG`: logs go to
stderr, so they do not pollute the MCP conversation.

---

## 9. Best practices to remember

- Use a dedicated Joomla account with the minimum permissions.
- Store the token in a `0600` file, an environment variable or a keychain —
  never in an AI client config file.
- Start in read-only mode, then enable writes.
- Always call `flexi_describe` before creating or updating.
- Use HTTPS in production, keep Basic Auth disabled, revoke the token if it
  leaks.
- Over HTTP, never expose the server port directly without an authenticated
  proxy.

The result: your AI assistant becomes a FLEXIcontent operator that respects the
same rules as your Joomla users, while remaining replaceable by any other
MCP-compatible client.
