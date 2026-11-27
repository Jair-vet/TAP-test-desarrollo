#!/usr/bin/env bash
# Cierra el túnel público y deja de mantener la Mac despierta.
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose --profile public stop tunnel >/dev/null 2>&1 || true

if [ -f .tunnel-caffeinate.pid ]; then
  kill "$(cat .tunnel-caffeinate.pid)" 2>/dev/null || true
  : > .tunnel-caffeinate.pid
fi

echo "✔ Túnel cerrado. La URL pública ya no responde."
