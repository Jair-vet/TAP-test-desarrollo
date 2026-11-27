# syntax=docker/dockerfile:1.7
# =============================================================================
# Imagen "web": compila Angular 19 y lo sirve con Nginx junto con Swagger UI.
# =============================================================================

# ---- 1) Build de Angular -------------------------------------------------
# --platform=$BUILDPLATFORM: el resultado son archivos estáticos (iguales en
# cualquier arquitectura), así que se compila nativo aunque la imagen final sea
# arm64. Evita emular Node con QEMU en la CD multi-arquitectura.
FROM --platform=$BUILDPLATFORM node:22.23.5-alpine3.23 AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

# ---- 2) Swagger UI (estático, sin CDN) -----------------------------------
FROM --platform=$BUILDPLATFORM node:22.23.5-alpine3.23 AS swagger
WORKDIR /sw
RUN --mount=type=cache,target=/root/.npm npm install --no-audit --no-fund swagger-ui-dist@5.36.0

# ---- 3) Runtime ----------------------------------------------------------
FROM nginx:1.28.3-alpine3.23
RUN rm /etc/nginx/conf.d/default.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/app.conf
COPY docker/nginx/snippets/ /etc/nginx/snippets/

COPY --from=build /app/dist/frontend/browser/ /usr/share/nginx/html/

COPY --from=swagger /sw/node_modules/swagger-ui-dist/swagger-ui.css \
     /sw/node_modules/swagger-ui-dist/swagger-ui-bundle.js \
     /sw/node_modules/swagger-ui-dist/swagger-ui-standalone-preset.js \
     /sw/node_modules/swagger-ui-dist/favicon-32x32.png \
     /usr/share/nginx/docs/
COPY docker/swagger/index.html docker/swagger/initializer.js /usr/share/nginx/docs/
COPY docs/openapi.yaml /usr/share/nginx/docs/openapi.yaml
COPY docs/postman/ /usr/share/nginx/docs/postman/

HEALTHCHECK --interval=15s --timeout=3s --retries=5 CMD wget -qO- http://127.0.0.1/ >/dev/null || exit 1
