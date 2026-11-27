#!/usr/bin/env bash
# =============================================================================
# Presupuestos de rendimiento contra el sistema levantado, todo en Docker:
#   1. API ...... prueba de carga con k6 (perf/k6/api-load.js):
#                 errores < 1 %, p95 < 300 ms, p99 < 800 ms
#   2. Frontend . Lighthouse CI (perf/lighthouse/lighthouserc.json):
#                 rendimiento, accesibilidad y buenas prácticas ≥ 90,
#                 FCP < 1.8 s, LCP < 2.5 s, CLS < 0.1, TBT < 200 ms
#
#   ./scripts/test-perf.sh           (o: make test-perf)
#
# Si se rompe un presupuesto el comando termina en error (y el CI falla).
# Reportes en reports/k6-summary.json y reports/lighthouse/.
# Las contraseñas viajan como variables de entorno, no como argumentos.
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")/.."

K6_IMAGE="grafana/k6:1.8.3"
NODE_IMAGE="node:22.23.5-alpine3.23"
LHCI_VERSION="0.16.1"
NETWORK="${COMPOSE_PROJECT_NAME:-tap-prueba}_frontend"
get() { grep -E "^$1=" .env | cut -d= -f2-; }

if ! docker network inspect "$NETWORK" >/dev/null 2>&1; then
  echo "✘ El sistema no está levantado. Ejecuta primero: make setup (o make up)"
  exit 1
fi

mkdir -p reports/lighthouse
TAP_ADMIN_PASSWORD="$(get SEED_ADMIN_PASSWORD)"
TAP_DEMO_PASSWORD="$(get SEED_DEMO_PASSWORD)"
export TAP_ADMIN_PASSWORD TAP_DEMO_PASSWORD

echo "▸ API: prueba de carga con k6"
docker run --rm --network "$NETWORK" --user "$(id -u):$(id -g)" \
  -e TAP_ADMIN_PASSWORD -e TAP_DEMO_PASSWORD -e BASE_URL=http://web/api/v1 \
  -v "$PWD/perf/k6:/scripts:ro" -v "$PWD/reports:/reports" \
  "$K6_IMAGE" run --quiet --summary-export=/reports/k6-summary.json /scripts/api-load.js

echo "▸ Frontend: Lighthouse CI (Chromium headless)"
# Comparte la red del contenedor web: la app se audita en http://localhost (contexto seguro)
WEB_CONTAINER="$(docker compose ps -q web)"
docker run --rm --network "container:${WEB_CONTAINER}" \
  -v "$PWD/perf/lighthouse:/config:ro" -v "$PWD/reports:/reports" -w /tmp \
  -e CHROME_PATH=/usr/bin/chromium -e LHCI_VERSION="$LHCI_VERSION" \
  "$NODE_IMAGE" sh -c '
    set -e
    apk add --no-cache chromium >/dev/null
    npx --yes "@lhci/cli@${LHCI_VERSION}" autorun --config=/config/lighthouserc.json
    chown -R '"$(id -u):$(id -g)"' /reports/lighthouse'
echo "✔ Rendimiento: API y frontend dentro de presupuesto"
