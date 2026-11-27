#!/usr/bin/env bash
# ¿Sigue vivo el túnel público? Revisa el contenedor y la URL guardada en .env.
set -euo pipefail
cd "$(dirname "$0")/.."

URL="$(grep -E '^PUBLIC_URL=' .env 2>/dev/null | cut -d= -f2- || true)"
STATE="$(docker inspect -f '{{.State.Status}} / {{if .State.Health}}{{.State.Health.Status}}{{else}}sin healthcheck{{end}}' "$(docker compose --profile public ps -q tunnel 2>/dev/null)" 2>/dev/null || echo 'no existe')"

echo "Contenedor tunnel: $STATE"
echo "URL en .env:       ${URL:-'(ninguna)'}"

if docker compose --profile public logs --since 10m tunnel 2>/dev/null | grep -q "Tunnel not found"; then
  echo "✘ Cloudflare eliminó este túnel (se perdió la conexión). Ejecuta: make public"
  exit 1
fi

if [ -n "$URL" ] && command -v dig >/dev/null 2>&1; then
  HOST="${URL#https://}"
  IP="$(dig +short @1.1.1.1 "$HOST" A 2>/dev/null | grep -E '^[0-9.]+$' | head -1 || true)"
  if [ -z "$IP" ]; then
    echo "✘ El subdominio ya no existe en DNS. Ejecuta: make public"
    exit 1
  fi
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 --resolve "$HOST:443:$IP" "$URL/api/v1/health" || true)"
  [ "$code" = "200" ] && echo "✔ La URL pública responde ($URL)" || { echo "✘ La URL respondió HTTP $code. Ejecuta: make public"; exit 1; }
fi
