#!/usr/bin/env bash
# =============================================================================
# Ejecuta la colección de Postman contra el sistema levantado (prueba de API de
# extremo a extremo) usando Newman en Docker: no hay que instalar nada.
#
#   ./scripts/test-api.sh            (o: make test-api)
#
# - Usa la red interna de Docker: http://web/api/v1
# - Inicia sesión con el admin demo del .env y corre las carpetas que no
#   alteran la cuenta del admin (no cambia contraseña ni activa 2FA).
# - Cada petición valida su código HTTP y su contenido (pestaña Tests), incluidos
#   los casos negativos del examen (precio de 4 dígitos, alta sin foto, correo
#   duplicado, sin token, inyección NoSQL). Si algo falla, el comando termina en error.
# - Las contraseñas viajan como variables de entorno del contenedor, no como
#   argumentos: así no aparecen en `ps` ni en los logs del CI.
# - Deja un reporte JUnit en reports/newman.xml (lo usa el CI).
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

NEWMAN_IMAGE="postman/newman:6.1.3-alpine"
NETWORK="${COMPOSE_PROJECT_NAME:-tap-prueba}_frontend"
get() { grep -E "^$1=" .env | cut -d= -f2-; }

if ! docker network inspect "$NETWORK" >/dev/null 2>&1; then
  echo "✘ El sistema no está levantado. Ejecuta primero: make setup (o make up)"
  exit 1
fi

TAP_PASSWORD="$(get SEED_ADMIN_PASSWORD)"
TAP_MAILBOX_PASSWORD="$(get MAILPIT_UI_PASSWORD)"
export TAP_PASSWORD TAP_MAILBOX_PASSWORD

mkdir -p reports
# shellcheck disable=SC2016  # las variables se expanden DENTRO del contenedor
docker run --rm --network "$NETWORK" \
  -e TAP_PASSWORD -e TAP_MAILBOX_PASSWORD \
  -v "$PWD/docs/postman:/etc/newman:ro" \
  -v "$PWD/reports:/reports" \
  --entrypoint sh "$NEWMAN_IMAGE" -c '
    exec newman run TAP-Prueba.postman_collection.json \
      --env-var "baseUrl=http://web/api/v1" \
      --env-var "email=admin@tap.test" \
      --env-var "password=$TAP_PASSWORD" \
      --env-var "mailboxPassword=$TAP_MAILBOX_PASSWORD" \
      --folder "Sistema" --folder "Sesión" --folder "Inicio" --folder "Bandeja de correos (demo)" \
      --folder "Productos" --folder "Perfiles" --folder "Usuarios" --folder "Bitácora" \
      --working-dir /etc/newman \
      --reporters cli,junit --reporter-junit-export /reports/newman.xml \
      --color on'
