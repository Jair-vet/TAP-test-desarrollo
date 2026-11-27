#!/usr/bin/env bash
# =============================================================================
# Auditoría de dependencias, todo en Docker (equivale al job "security" del CI):
#   1. composer audit ... backend; los avisos conocidos de Laravel 11 están
#                         revisados y justificados en composer.json (config.audit.ignore)
#   2. audit-ci ......... frontend (dependencias de producción, moderate o mayor);
#                         excepciones de Angular 19 justificadas en frontend/audit-ci.jsonc
#
#   ./scripts/test-security.sh       (o: make audit)
#
# Cualquier aviso NUEVO hace fallar el comando. No requiere el sistema levantado.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

IMAGE="tap-prueba/php-dev"
NODE_IMAGE="node:22.23.5-alpine3.23"

echo "▸ Backend: composer audit"
docker build -q -f docker/php/Dockerfile --target dev -t "$IMAGE" . >/dev/null
docker run --rm -v "$PWD/backend:/var/www/html" "$IMAGE" composer audit --locked --no-interaction --format=plain

echo "▸ Frontend: audit-ci"
docker run --rm -v "$PWD/frontend:/app" -w /app "$NODE_IMAGE" npx --yes audit-ci@7.1.0 --config audit-ci.jsonc
echo "✔ Dependencias sin avisos nuevos"
