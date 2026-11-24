#!/usr/bin/env bash
# Dev-only launcher: read the DDEV API token from the Bruno environment file
# so the OpenCode config does not need to contain a secret.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BRUNO_ENV="/home/yannick/www/flexi-dev/bruno/flexicontent-api/environments/dev.bru"

if [ ! -f "$BRUNO_ENV" ]; then
  echo "Bruno env introuvable : $BRUNO_ENV" >&2
  exit 1
fi

export FLEXI_API_TOKEN="$(
  awk -F': *' '/^[[:space:]]+admin_token:/ {print $2; exit}' "$BRUNO_ENV"
)"

exec "$HERE/.venv/bin/python" "$HERE/run.py" "$@"
