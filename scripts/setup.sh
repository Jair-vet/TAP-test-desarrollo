#!/usr/bin/env bash
# Prepara .env con secretos aleatorios (solo la primera vez) y levanta el stack.
set -euo pipefail
cd "$(dirname "$0")/.."

# Sin `| head` (con pipefail podría cortar la tubería con SIGPIPE): se recorta con bash
rand() { local s; s="$(openssl rand -base64 96 | tr -dc 'A-Za-z0-9')"; printf '%s' "${s:0:${1:-32}}"; }
# Contraseña que cumple la política (mayúscula, minúscula, número y símbolo)
demo_pass() { printf 'Tap%s#%s9' "$(rand 8)" "$(rand 4)"; }
# Reemplaza KEY=valor en .env sin dejar archivos de respaldo (perl es portable macOS/Linux)
set_var() { KEY="$1" VAL="$2" perl -pi -e 's/^\Q$ENV{KEY}\E=.*/$ENV{KEY}=$ENV{VAL}/' .env; }

if [ ! -f .env ]; then
  cp .env.example .env
  set_var APP_KEY "base64:$(openssl rand -base64 32)"
  set_var MONGO_ROOT_PASSWORD "$(rand 32)"
  set_var MONGO_APP_PASSWORD "$(rand 32)"
  set_var REDIS_PASSWORD "$(rand 32)"
  set_var MAILPIT_UI_PASSWORD "$(rand 16)"
  set_var SEED_ADMIN_PASSWORD "$(demo_pass)"
  set_var SEED_DEMO_PASSWORD "$(demo_pass)"
  chmod 600 .env
  echo "✔ .env creado con secretos aleatorios"
else
  echo "✔ .env ya existe (no se modifica)"
fi

docker compose build
docker compose up -d --wait
# En CI no se imprimen contraseñas en los logs
[ -n "${CI:-}" ] || ./scripts/credentials.sh
