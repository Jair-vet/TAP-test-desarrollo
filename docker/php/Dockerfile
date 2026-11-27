# syntax=docker/dockerfile:1.7
# =============================================================================
# Imagen del backend (Laravel 11 + PHP 8.2-FPM)
#
#   base  -> PHP 8.2 + extensiones (mongodb, redis, gd, zip, intl, opcache...)
#   dev   -> base + Composer + dependencias de desarrollo (tests, Pint, Larastan)
#   prod  -> base + código + vendor sin dev, autoloader optimizado y OPcache
#
# Versiones fijadas a propósito (reproducibilidad): verificadas en Docker Hub
# y PECL el 25-11-2026.
# =============================================================================
ARG PHP_IMAGE=php:8.2.36-fpm-alpine3.23

FROM ${PHP_IMAGE} AS base

ARG PECL_MONGODB=2.5.8
ARG PECL_REDIS=6.3.0

# Dependencias de ejecución + compilación de extensiones en una sola capa
RUN set -eux; \
    apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp freetype fcgi tini; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev \
        libjpeg-turbo-dev libwebp-dev freetype-dev openssl-dev linux-headers; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j"$(nproc)" gd zip intl opcache pcntl bcmath; \
    pecl install "mongodb-${PECL_MONGODB}" "redis-${PECL_REDIS}"; \
    docker-php-ext-enable mongodb redis; \
    apk del .build-deps; \
    rm -rf /tmp/pear ~/.pearrc

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
FROM base AS dev
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer
# En desarrollo OPcache revalida archivos en cada petición
RUN printf "opcache.validate_timestamps=1\nallow_url_fopen=On\nmemory_limit=1G\n" > /usr/local/etc/php/conf.d/zzz-dev.ini

# -----------------------------------------------------------------------------
FROM dev AS vendor
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# -----------------------------------------------------------------------------
FROM base AS prod
ENV APP_ENV=production

COPY --from=vendor /var/www/html/vendor ./vendor
COPY backend/ ./
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
RUN set -eux; \
    composer dump-autoload --no-dev --classmap-authoritative --no-interaction; \
    rm /usr/bin/composer; \
    mkdir -p storage/framework/cache storage/framework/views storage/logs storage/app/private storage/jwt bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=5 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["/sbin/tini", "--", "entrypoint"]
CMD ["php-fpm"]
