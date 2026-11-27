#!/usr/bin/env bash
# =============================================================================
# Calidad del frontend, todo en Docker (equivale al job "frontend" del CI):
#   1. ESLint (angular-eslint) ... convenciones de Angular
#   2. Karma + Jasmine ........... pruebas unitarias en Chromium headless
#   3. ng build .................. build de producción
#
#   ./scripts/test-front.sh          (o: make test-front)
#
# No requiere el sistema levantado. node_modules vive en un volumen de Docker
# (tap-node-modules), así no se mezcla con tu equipo.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

NODE_IMAGE="node:22.23.5-alpine3.23"

docker run --rm -v "$PWD/frontend:/app" -v tap-node-modules:/app/node_modules -w /app \
  -e CHROME_BIN=/usr/bin/chromium -e NODE_OPTIONS=--max-old-space-size=3072 \
  "$NODE_IMAGE" sh -c '
    set -e
    apk add --no-cache chromium >/dev/null
    npm ci --no-audit --no-fund
    echo "▸ ESLint";                                   npm run lint
    echo "▸ Pruebas unitarias (Karma + Chromium)";     npm run test:ci
    echo "▸ Build de producción";                      npm run build'
echo "✔ Frontend: lint, pruebas y build en verde"
