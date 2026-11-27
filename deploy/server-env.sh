#!/usr/bin/env bash
# Crea/actualiza el .env DEL SERVIDOR (se ejecuta en el servidor, dentro de ~/tap-prueba).
#   - Secretos de infraestructura (APP_KEY, Mongo, Redis): se generan AQUÍ, nuevos y
#     aleatorios, solo la primera vez. Nunca salen de tu Mac ni se reutilizan.
#   - Contraseñas de usuarios demo y de la bandeja: llegan en .env.seed (desde tu
#     .env local) para que la lista de credenciales que ya tienes siga sirviendo.
#   - Dominio público: argumento $1.
set -euo pipefail
cd "$(dirname "$0")/.."
DOMAIN="${1:?Falta el dominio}"

rand() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-32}"; }
set_var() {
  if grep -q "^$1=" .env; then
    KEY="$1" VAL="$2" perl -pi -e 's/^\Q$ENV{KEY}\E=.*/$ENV{KEY}=$ENV{VAL}/' .env
  else
    printf '%s=%s\n' "$1" "$2" >> .env
  fi
}

umask 077
if [ ! -f .env ]; then
  cp .env.example .env
  set_var APP_KEY "base64:$(openssl rand -base64 32)"
  set_var MONGO_ROOT_PASSWORD "$(rand 32)"
  set_var MONGO_APP_PASSWORD "$(rand 32)"
  set_var REDIS_PASSWORD "$(rand 32)"
  set_var MAILPIT_UI_PASSWORD "$(rand 16)"
  set_var SEED_ADMIN_PASSWORD "Tap$(rand 8)#$(rand 4)9"
  set_var SEED_DEMO_PASSWORD "Tap$(rand 8)#$(rand 4)9"
fi

if [ -f .env.seed ]; then
  while IFS='=' read -r key value; do
    case "$key" in
      SEED_ADMIN_PASSWORD|SEED_DEMO_PASSWORD|MAILPIT_UI_USER|MAILPIT_UI_PASSWORD) [ -n "$value" ] && set_var "$key" "$value" ;;
    esac
  done < .env.seed
  : > .env.seed
fi

set_var PUBLIC_HOST "$DOMAIN"
set_var APP_URL "https://$DOMAIN"
set_var FRONTEND_URL "https://$DOMAIN"
set_var PUBLIC_URL "https://$DOMAIN"
chmod 600 .env
echo "  · .env listo para https://$DOMAIN"
