#!/usr/bin/env bash
# Muestra URLs y credenciales de la demo leyendo el .env local.
set -euo pipefail
cd "$(dirname "$0")/.."
get() { grep -E "^$1=" .env | cut -d= -f2- || true; }
PORT="$(get WEB_PORT)"; PORT="${PORT:-8080}"
PUBLIC_URL="$(get PUBLIC_URL)"
cat <<TXT

  ┌────────────────────────────────────────────────────────────────────┐
    Sistema:   http://localhost:${PORT}
    Swagger:   http://localhost:${PORT}/docs/
    Correos:   http://localhost:${PORT}/mail/  (usuario: $(get MAILPIT_UI_USER) / $(get MAILPIT_UI_PASSWORD))
    Público:   ${PUBLIC_URL:-"(ejecuta: make public)"}

    admin@tap.test     $(get SEED_ADMIN_PASSWORD)   → Administrador
    almacen@tap.test   $(get SEED_DEMO_PASSWORD)   → Almacén
    consulta@tap.test  $(get SEED_DEMO_PASSWORD)   → Consulta
    rh@tap.test        $(get SEED_DEMO_PASSWORD)   → Recursos Humanos
  └────────────────────────────────────────────────────────────────────┘
TXT
