# Despliegue: Docker y túnel de Cloudflare

## 1. Requisitos
- Docker Desktop (Compose v2). Nada más: PHP, Composer, Node y MongoDB corren dentro de contenedores.

## 2. Servicios (`docker-compose.yml`)

| Servicio | Imagen | Función |
|---|---|---|
| `web` | `nginx:1.28.3-alpine3.23` + build de Angular + Swagger UI | Sirve la SPA, gateway `/api` → PHP-FPM, `/docs`, `/mail`. Publica `127.0.0.1:8080` |
| `api` | `php:8.2.36-fpm-alpine3.23` (+ mongodb 2.5.8, redis, gd, intl, opcache/JIT) | Laravel. Al arrancar: cachés, llaves JWT, migraciones, seeder |
| `worker` | misma que `api` | `queue:work` (envío de correos) |
| `mongo` | `mongo:8.0.38-noble` | Base de datos (red interna) |
| `redis` | `redis:7.4.12-alpine` | Caché, colas, rate limit, denylist de JWT (red interna) |
| `mailpit` | `axllent/mailpit:v1.34.0` | Bandeja de correo de pruebas en `/mail/` (protegida por Nginx con `auth_request`; acceso en `/correos`) |
| `tunnel` | `cloudflare/cloudflared:2026.11.2` | Perfil `public`: túnel HTTPS |

Volúmenes persistentes: `mongo-data`, `redis-data`, `jwt-keys`, `photos`.

## 3. Comandos

```bash
make setup        # 1ª vez: .env con secretos + build + up
make up / down    # levantar / detener (conserva datos)
make restart      # reconstruir imágenes tras cambiar código
make logs         # logs de api, worker y web
make reset        # ⚠ borra volúmenes (datos) y vuelve a sembrar
make test         # pruebas y linters de backend y frontend
```

## 4. Publicar para los evaluadores (Cloudflare Tunnel)

### Opción A — Quick Tunnel (sin cuenta, recomendado para la demo)

```bash
make public
# 🌐 URL pública: https://algo-aleatorio.trycloudflare.com
```

- `cloudflared` abre una conexión **saliente** a Cloudflare; no se abre ningún puerto del router ni se
  necesita IP pública. El tráfico llega por **HTTPS** (certificado de Cloudflare) → `web:80`.
- `make public` **siempre crea un túnel nuevo**, espera a que Cloudflare lo registre, comprueba la URL
  por HTTPS (consultando el DNS 1.1.1.1 para no cachear un NXDOMAIN en tu Mac) y solo entonces la muestra.
- El script guarda la URL en `.env` (`PUBLIC_URL`, `FRONTEND_URL`) y recrea `api/worker` para que el
  botón de los correos apunte a la URL pública.
- En macOS ejecuta `caffeinate` para que la Mac **no se duerma** mientras el túnel esté publicado.
- `make public-status` dice si la URL sigue viva; `make unpublish` cierra el túnel y libera la Mac.
- **Limitaciones** (documentadas por Cloudflare): el túnel es **efímero** — si la conexión se cae unos
  minutos (Mac suspendida, cambio de red), Cloudflare **borra el túnel y su subdominio** y la URL da
  `DNS_PROBE_FINISHED_NXDOMAIN`; hay que correr `make public` de nuevo (URL nueva). Además: máximo 200
  peticiones simultáneas, sin SLA, sin Server-Sent Events. Sirve para la demo; para dejarlo publicado
  varios días usa la Opción B.

### Opción B — Túnel con nombre y dominio fijo (cuenta gratuita de Cloudflare)

1. Agrega tu dominio a Cloudflare.
2. Zero Trust → Networks → Tunnels → *Create tunnel* → copia el token.
3. Public hostname: `tap.tudominio.com` → `http://web:80`.
4. En `docker-compose.yml`, servicio `tunnel`:
   ```yaml
   command: ["tunnel", "--no-autoupdate", "run"]
   environment:
     TUNNEL_TOKEN: ${CLOUDFLARE_TUNNEL_TOKEN}
   ```
5. `.env`: `CLOUDFLARE_TUNNEL_TOKEN=...`, `FRONTEND_URL=https://tap.tudominio.com` → `make public`.

Opcional: Cloudflare Access (login por correo/OTP) delante del sitio para que solo los evaluadores entren.

### Alternativas evaluadas
| Opción | Por qué no se eligió como principal |
|---|---|
| ngrok | Requiere cuenta/token; plan gratis con página intersticial |
| localtunnel | Sin mantenimiento activo, página de advertencia |
| Tailscale Funnel | Requiere cuenta e instalar Tailscale en la Mac |
| VPS / nube | Costo y tiempo; el examen permite servidor local |

## 5. Producción real (si se llevara más allá de la demo)
- Mongo en replica set (o Atlas) y Redis gestionado; `w: majority` ya está configurado.
- Llaves JWT y `APP_KEY` en un gestor de secretos; rotación de llaves por `kid`.
- SMTP real/SES en `MAIL_*`; fotos en S3 (`PhotoStorage`).
- Imágenes publicadas en un registry desde CI y despliegue con Compose/Kubernetes.
- Observabilidad: logs JSON a un agregador usando `request_id`.

## 6. Solución de problemas

| Síntoma | Causa / solución |
|---|---|
| `Ejecuta make setup` al usar compose | Falta `.env` → `make setup` |
| `make public` no muestra URL | `docker compose --profile public logs tunnel`; reintenta (red corporativa puede bloquear el puerto 7844) |
| La URL pública da `DNS_PROBE_FINISHED_NXDOMAIN` | El Quick Tunnel murió (en los logs: `Tunnel not found`). Confírmalo con `make public-status` y crea otro con `make public`. Si abriste la URL nueva antes de que propagara y tu Mac guardó el error: `sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder` o espera 1-2 min |
| `/mail/` me manda a `/correos` | Es intencional (los correos traen contraseñas). Entra con usuario `revisor` y la contraseña `MAILPIT_UI_PASSWORD` del `.env` (`make credentials`), o desde el menú "Correos (demo)" si tu sesión tiene permiso de alta de usuarios. El acceso dura 2 h (cookie `tap_mail`) |
| Al recargar la página pide iniciar sesión otra vez | La cookie `tap_rt` se limita a `/api/v1/auth`; revisa que el navegador no bloquee cookies del sitio. Por HTTPS (túnel) lleva `Secure`; por `http://localhost` no |
| No llegan correos | `docker compose logs worker`; revisa `/mail/` |
| Cambié código y no se ve | `make restart` (las imágenes copian el código al construirse) |
| Quiero empezar de cero | `make reset` |
