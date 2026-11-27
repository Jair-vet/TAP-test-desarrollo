#!/bin/sh
# Arranque del contenedor PHP. CONTAINER_ROLE=api|worker
set -eu
cd /var/www/html

# Cachés de producción: config, rutas, vistas y eventos precompilados
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan event:cache --quiet

if [ "${CONTAINER_ROLE:-api}" = "api" ]; then
  php artisan security:jwt-keys
  # Mongo ya está "healthy" (depends_on), pero reintentamos por si tarda en aceptar auth
  n=0
  until php artisan migrate --force; do
    n=$((n+1)); [ "$n" -ge 10 ] && exit 1
    echo "Esperando a MongoDB... ($n)"; sleep 3
  done
  php artisan db:seed --force
fi

exec "$@"
