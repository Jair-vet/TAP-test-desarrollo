#!/usr/bin/env bash
# =============================================================================
# Calidad del backend, todo en Docker (equivale al job "backend" del CI):
#   1. Pint ........ estilo PSR-12
#   2. Larastan .... análisis estático nivel 6
#   3. PHPUnit ..... pruebas unitarias (dominio/casos de uso) y de API contra
#                    MongoDB real, en una BD aislada (tap_test)
#
#   ./scripts/test-back.sh           (o: make test-back)
#
# Requiere el sistema levantado (usa el contenedor de MongoDB).
# La contraseña de Mongo viaja como variable de entorno, no como argumento.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

IMAGE="tap-prueba/php-dev"
NETWORK="${COMPOSE_PROJECT_NAME:-tap-prueba}_backend"
get() { grep -E "^$1=" .env | cut -d= -f2-; }

[ -f .env ] || { echo "✘ Falta .env. Ejecuta primero: ./scripts/setup.sh (o make setup)"; exit 1; }
if ! docker network inspect "$NETWORK" >/dev/null 2>&1; then
  echo "✘ El sistema no está levantado. Ejecuta primero: make up (o docker compose up -d --wait)"
  exit 1
fi

echo "▸ Imagen de desarrollo de PHP 8.2 (con Composer)…"
docker build -q -f docker/php/Dockerfile --target dev -t "$IMAGE" . >/dev/null

MONGODB_URI="mongodb://$(get MONGO_ROOT_USER):$(get MONGO_ROOT_PASSWORD)@mongo:27017/?authSource=admin"
export MONGODB_URI

docker run --rm --network "$NETWORK" -e MONGODB_URI \
  -v "$PWD/backend:/var/www/html" "$IMAGE" sh -c '
    set -e
    composer install -q --no-interaction
    echo "▸ Pint (PSR-12)";        vendor/bin/pint --test
    echo "▸ Larastan (nivel 6)";   vendor/bin/phpstan analyse --no-progress --memory-limit=1G
    echo "▸ PHPUnit";              vendor/bin/phpunit --testdox'
echo "✔ Backend: estilo, análisis estático y pruebas en verde"
