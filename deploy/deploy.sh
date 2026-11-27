#!/usr/bin/env bash
# =============================================================================
# Despliega el proyecto en un servidor Ubuntu (Oracle Cloud Free, Hetzner, etc.)
# desde tu Mac, en un solo comando:
#
#   ./deploy/deploy.sh ubuntu@IP_DEL_SERVIDOR tap-prueba.duckdns.org
#
# Requisitos: acceso SSH con llave al servidor y un dominio que apunte a su IP
# (DuckDNS gratis: ver docs/HOSTING.md). Es idempotente: vuelve a correrlo para
# subir cambios.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

TARGET="${1:?Uso: ./deploy/deploy.sh usuario@IP dominio}"
DOMAIN="${2:?Falta el dominio público (ej. tap-prueba.duckdns.org)}"
REMOTE_DIR="tap-prueba"
# Llave SSH opcional: SSH_KEY=~/.ssh/id_ed25519 ./deploy/deploy.sh ...
SSH_OPTS="-o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30"
if [ -n "${SSH_KEY:-}" ]; then
  [ -f "$SSH_KEY" ] || { echo "No existe la llave $SSH_KEY"; exit 1; }
  SSH_OPTS="$SSH_OPTS -i $SSH_KEY -o IdentitiesOnly=yes"
fi
# shellcheck disable=SC2206
SSH=(ssh $SSH_OPTS "$TARGET")

echo "→ 0/5 Probando acceso SSH a $TARGET..."
# shellcheck disable=SC2086
if ! ssh $SSH_OPTS -o ConnectTimeout=15 -o BatchMode=yes "$TARGET" true 2>/dev/null; then
  echo "✘ No se pudo entrar por SSH. Revisa la IP, el usuario (Oracle: ubuntu) y la llave (SSH_KEY=...)."
  exit 1
fi

get() { grep -E "^$1=" .env 2>/dev/null | cut -d= -f2- || true; }

echo "→ 1/5 Preparando el servidor (Docker, swap, firewall)..."
"${SSH[@]}" 'sudo bash -s' < deploy/server-setup.sh

echo "→ 2/5 Copiando el proyecto (sin .env, dependencias ni datos locales)..."
"${SSH[@]}" "mkdir -p $REMOTE_DIR"
rsync -az --delete -e "ssh $SSH_OPTS" \
  --exclude '.git/' --exclude '.env' --exclude '.env.seed' --exclude 'node_modules/' \
  --exclude 'backend/vendor/' --exclude 'frontend/.angular/' --exclude 'frontend/dist/' \
  --exclude 'backend/storage/jwt/' --exclude '.tunnel-caffeinate.pid' --exclude '.DS_Store' \
  ./ "$TARGET:$REMOTE_DIR/"

echo "→ 3/5 Configurando variables del servidor..."
# Por stdin (no como argumento): no quedan en el historial ni en la lista de procesos
printf 'SEED_ADMIN_PASSWORD=%s\nSEED_DEMO_PASSWORD=%s\nMAILPIT_UI_USER=%s\nMAILPIT_UI_PASSWORD=%s\n' \
  "$(get SEED_ADMIN_PASSWORD)" "$(get SEED_DEMO_PASSWORD)" "$(get MAILPIT_UI_USER)" "$(get MAILPIT_UI_PASSWORD)" \
  | "${SSH[@]}" "umask 077 && cat > $REMOTE_DIR/.env.seed"
"${SSH[@]}" "bash $REMOTE_DIR/deploy/server-env.sh '$DOMAIN'"

echo "→ 4/5 Construyendo y levantando (la primera vez tarda ~5-10 min)..."
"${SSH[@]}" "cd $REMOTE_DIR && sudo docker compose -f docker-compose.yml -f deploy/docker-compose.server.yml up -d --build --wait --wait-timeout 600"

echo "→ 5/5 Verificando https://$DOMAIN ..."
ok=""
for _ in $(seq 1 30); do
  code="$("${SSH[@]}" "curl -s -o /dev/null -w '%{http_code}' --max-time 10 https://$DOMAIN/api/v1/health" || true)"
  if [ "$code" = "200" ]; then ok=1; break; fi
  sleep 5
done

echo ""
if [ -n "$ok" ]; then
  echo "  ✔ Publicado con HTTPS: https://$DOMAIN"
  echo "     Swagger: https://$DOMAIN/docs/   ·   Correos: https://$DOMAIN/mail/"
else
  echo "  ⚠ Aún no responde por HTTPS. Revisa que el dominio apunte a la IP del servidor"
  echo "    y que los puertos 80/443 estén abiertos en el panel del proveedor."
  echo "    Logs: ssh $TARGET 'cd $REMOTE_DIR && sudo docker compose logs --tail 50 caddy'"
fi
echo "  Las credenciales demo son las mismas de ~/Downloads/credenciales-prueba-tap.txt"
