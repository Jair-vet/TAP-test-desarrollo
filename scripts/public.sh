#!/usr/bin/env bash
# Expone el sistema por HTTPS con un Cloudflare Quick Tunnel (sin cuenta ni dominio).
#
#  1. SIEMPRE crea un túnel nuevo (--force-recreate). Los Quick Tunnels son
#     efímeros: si la conexión se cayó (Mac suspendida, sin red), Cloudflare ya
#     borró el subdominio anterior aunque el contenedor diga "Up".
#  2. Lee la URL SOLO de los logs de este arranque (nunca una URL vieja).
#  3. Espera a que el túnel esté "healthy" (conexión registrada con Cloudflare).
#  4. Comprueba la URL por HTTPS consultando el DNS de Cloudflare (1.1.1.1)
#     directamente, para no "envenenar" la caché DNS de tu Mac con un NXDOMAIN
#     si el subdominio aún se está propagando.
#  5. Guarda la URL en .env (PUBLIC_URL/FRONTEND_URL) y recrea api/worker para
#     que el botón de los correos apunte a la URL pública.
#  6. En macOS mantiene la Mac despierta (caffeinate) mientras esté publicado.
set -euo pipefail
cd "$(dirname "$0")/.."

set_var() {
  if grep -q "^$1=" .env; then
    KEY="$1" VAL="$2" perl -pi -e 's/^\Q$ENV{KEY}\E=.*/$ENV{KEY}=$ENV{VAL}/' .env
  else
    printf '%s=%s\n' "$1" "$2" >> .env
  fi
}

since="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "→ Creando un túnel nuevo de Cloudflare..."
docker compose --profile public up -d --force-recreate --wait --wait-timeout 90 tunnel >/dev/null

URL=""
for _ in $(seq 1 30); do
  URL="$(docker compose --profile public logs --since "$since" tunnel 2>/dev/null \
    | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -1 || true)"
  [ -n "$URL" ] && break
  sleep 2
done
if [ -z "$URL" ]; then
  echo "✘ No se obtuvo la URL. Revisa: docker compose --profile public logs tunnel"
  exit 1
fi
HOST="${URL#https://}"

echo "→ Verificando que $URL responda (puede tardar ~10-30 s)..."
ok=""
if command -v dig >/dev/null 2>&1; then
  for _ in $(seq 1 30); do
    IP="$(dig +short @1.1.1.1 "$HOST" A 2>/dev/null | grep -E '^[0-9.]+$' | head -1 || true)"
    if [ -n "$IP" ]; then
      code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 --resolve "$HOST:443:$IP" "$URL/api/v1/health" || true)"
      if [ "$code" = "200" ]; then ok=1; break; fi
    fi
    sleep 3
  done
else
  sleep 20
fi

set_var PUBLIC_URL "$URL"
set_var FRONTEND_URL "$URL"
docker compose up -d --no-deps --force-recreate api worker >/dev/null
docker compose up -d --wait >/dev/null

# Mantener la Mac despierta mientras el túnel esté publicado (se detiene con make unpublish)
if command -v caffeinate >/dev/null 2>&1; then
  if [ -f .tunnel-caffeinate.pid ] && kill -0 "$(cat .tunnel-caffeinate.pid)" 2>/dev/null; then
    :
  else
    nohup caffeinate -ims >/dev/null 2>&1 &
    echo $! > .tunnel-caffeinate.pid
  fi
fi

echo ""
if [ -n "$ok" ]; then
  echo "  ✔ Túnel verificado por HTTPS"
else
  echo "  ⚠ No se pudo confirmar la URL desde aquí; ábrela en 1 minuto."
fi
echo "  🌐 URL pública: $URL"
echo "     Swagger:     $URL/docs/"
echo "     Correos:     $URL/mail/"
echo ""
echo "  • La URL cambia cada vez que se crea un túnel (para una fija: docs/DESPLIEGUE.md)."
echo "  • Si la Mac se suspende o pierde red varios minutos, Cloudflare borra el túnel:"
echo "    vuelve a correr 'make public'. Mientras está publicado, la Mac no se dormirá."
echo "  • Estado en cualquier momento: make public-status · Cerrar: make unpublish"
