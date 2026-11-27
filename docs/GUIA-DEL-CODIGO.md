# Guía del código — dónde está cada cosa

> Úsala en la revisión en vivo: "mira, aquí está esto; si te piden cambiar aquello, se hace aquí".

## 1. Mapa rápido

### Backend (`backend/`)

| Tema | Archivo |
|---|---|
| **Rutas / endpoints** (todas, con su permiso) | `routes/api.php` |
| Middlewares globales, prefijo `/api/v1`, manejo de errores | `bootstrap/app.php` |
| **Qué adaptador implementa cada puerto** (composition root) | `app/Providers/HexagonalServiceProvider.php` |
| Rate limiting (login, 2FA, recuperación, API) | `app/Providers/AppServiceProvider.php` |
| Parámetros de seguridad (duración de tokens, intentos, 2FA obligatorio…) | `config/security.php` (+ variables en `.env`) |
| Conexión a MongoDB y Redis | `config/database.php` |
| Hash de contraseñas (Argon2id) | `config/hashing.php` |
| Colecciones, validadores `$jsonSchema` e índices | `database/migrations/2026_11_25_000001_create_mongo_collections.php` |
| Datos demo (perfiles, usuarios, 36 productos) | `database/seeders/DatabaseSeeder.php` |
| Plantilla del PDF exportado | `resources/views/exports/table.blade.php` |
| Plantilla del correo de credenciales | `resources/views/mail/credentials.blade.php` |
| Acceso a la bandeja de correos de prueba (`/mail/` vía `/correos`) | `src/Support/Infrastructure/Http/MailboxController.php`, `MailboxPass.php` + `location ^~ /mail/` en `docker/nginx/default.conf` |

**Productos** — `src/Catalog/`

| Capa | Archivo | Qué hace |
|---|---|---|
| Dominio | `Domain/Product.php`, `Domain/Price.php` | Reglas: nombre/marca obligatorios, precio ≤ 999.99 |
| Puerto | `Domain/ProductRepository.php` | Interfaz de persistencia |
| Caso de uso | `Application/ProductService.php` | Alta, edición, baja, listado (caché), exportación + bitácora |
| HTTP | `Infrastructure/Http/ProductController.php`, `ProductRequest.php`, `ProductPresenter.php` | Endpoint, validación, forma del JSON |
| Mongo | `Infrastructure/Persistence/MongoProductRepository.php` | Documento, búsqueda, orden, paginación |

**Usuarios, perfiles, autenticación** — `src/IdentityAccess/`

| Tema | Archivo |
|---|---|
| Entidad usuario (bloqueo, contraseña temporal, 2FA, `tokenVersion`) | `Domain/User/User.php` |
| Secciones y acciones (catálogo de permisos) | `Domain/Profile/Section.php`, `Action.php` |
| Unión de permisos de varios perfiles | `Domain/Profile/PermissionSet.php` |
| Login, 2FA, refresh, logout | `Application/Auth/SessionService.php` |
| Recuperación y cambio de contraseña | `Application/Auth/PasswordService.php` |
| Activar/desactivar 2FA, códigos de respaldo | `Application/TwoFactor/TwoFactorService.php` |
| Alta/edición de usuarios (foto, correo de bienvenida) | `Application/User/UserService.php` |
| Perfiles | `Application/Profile/ProfileService.php` |
| JWT (EdDSA) | `Infrastructure/Security/EdDsaJwtTokenService.php`, `JwtKeyStore.php` |
| Middleware JWT / permisos / cuenta lista | `Infrastructure/Http/Middleware/*.php` |
| Cookie del refresh token | `Infrastructure/Http/RefreshCookie.php` |
| Fotos (re-codificación WebP) y URL firmadas | `Infrastructure/Storage/LocalPhotoStorage.php`, `MediaUrlSigner.php` |
| Correo de credenciales (en cola, cifrado) | `Infrastructure/Mail/*.php` |

**Bitácora** — `src/Audit/` (`Application/AuditService.php`, `Domain/ChangeSet.php` calcula las diferencias).

**Transversal** — `src/Shared/` (errores → JSON en `Infrastructure/Http/ApiExceptionRenderer.php`,
anti-inyección NoSQL en `Infrastructure/Http/Middleware/RejectNoSqlOperators.php`, utilidades Mongo en
`Infrastructure/Persistence/Mongo/Mongo.php`, caché en `Infrastructure/Cache/VersionedQueryCache.php`,
PDF/Excel en `Infrastructure/Export/TabularFileExporter.php`).

### Frontend (`frontend/src/app/`)

| Tema | Archivo |
|---|---|
| **Rutas** (lazy loading + guards por sección) | `app.routes.ts` |
| Proveedores globales (HTTP, interceptores, transiciones, íconos, restaurar sesión) | `app.config.ts` |
| **URL base de la API** | `core/http/api.config.ts` |
| Sesión en memoria y permisos (`can()`) | `core/auth/auth.store.ts` |
| Llamadas de login/2FA/refresh/logout | `core/auth/auth.service.ts` |
| Guards (sesión, invitado, permiso por sección) | `core/auth/auth.guards.ts` |
| Bearer + refresh automático ante 401 | `core/http/auth.interceptor.ts` |
| Toasts de error globales | `core/http/error.interceptor.ts` |
| Descargas PDF/Excel | `core/http/download.service.ts` |
| Menú lateral filtrado por permisos | `layout/shell.component.ts` / `.html` |
| Tabla reutilizable: búsqueda, orden, paginación | `shared/utils/list-controller.ts` + `shared/ui/*` |
| Ocultar botones sin permiso | `shared/directives/can.directive.ts` (`*appCan="'products:update'"`) |
| Formato DD/MM/YYYY HH:MM | `shared/pipes/app-date.pipe.ts` |
| Colores, botones, tabla, animaciones (Tailwind v4) | `src/styles.css` |
| Color e ícono por sección y acción (perfiles) | `shared/utils/sections.ts` + clases `.tone-*` en `src/styles.css` |
| Efectos: inclinación 3D, parallax, aparición al hacer scroll, contador | `shared/directives/{tilt,parallax,reveal,count-up}.directive.ts` |
| Escena 3D de contenedores (login, inicio) | `shared/ui/port-scene.component.ts` |
| Validadores de formularios (espejo del backend) | `shared/utils/validators.ts` |
| Tamaño de los modales (`sm/md/lg/xl`) | `core/ui/dialog.service.ts` + `.dialog-*` en `src/styles.css` |
| Pantalla de acceso a la bandeja de correos | `features/mailbox/` (ruta pública `/correos`) |
| Íconos registrados | `app.icons.ts` |
| Pantallas | `features/<sección>/` → `*.api.ts` (endpoints), `*.page.ts` (tabla), `*.dialog.ts` (modales) |

## 2. Recetario: "si te piden…"

### …cambiar o agregar un endpoint
1. Ruta en `backend/routes/api.php` (con su `->middleware('permission:sección,acción')`).
2. Método en el controller del contexto (`src/<Contexto>/Infrastructure/Http/...Controller.php`).
3. Lógica en el servicio de aplicación (`src/<Contexto>/Application/...Service.php`).
4. En Angular, la URL vive **solo** en el `*.api.ts` de la feature (ej. `features/products/products.api.ts`).
5. Documenta en `docs/openapi.yaml` y regenera Postman: `node docs/postman/build-collection.mjs`.

### …agregar un campo a productos (ej. "stock")
1. Dominio: propiedad + validación en `Catalog/Domain/Product.php` (y en `snapshot()` para la bitácora).
2. DTO: `Catalog/Application/ProductData.php`; servicio: pásalo en `create/update`.
3. HTTP: regla en `ProductRequest::rules()` y `toData()`; salida en `ProductPresenter`.
4. Mongo: `MongoProductRepository::save()` y `hydrate()`; si es obligatorio, agrégalo al `$jsonSchema`
   de la migración (nueva migración con `collMod`).
5. Angular: interfaz `Product` en `products.api.ts`, control en `product-form.dialog.ts`, columna en
   `products.page.html`, y columna en `ProductService::export()` para PDF/Excel.

### …cambiar la regla del precio (ej. solo enteros de 3 dígitos)
`Catalog/Domain/Price.php` (constante `PATTERN`), `ProductRequest` (regex) y `PRICE_PATTERN` en
`product-form.dialog.ts`.

### …agregar una sección nueva al sistema de permisos (ej. "Reportes")
1. `case Reports = 'reports'` en `IdentityAccess/Domain/Profile/Section.php` (+ etiqueta y acciones).
2. Agrégala al `enum` del `$jsonSchema` de perfiles (migración nueva).
3. Protege sus rutas con `permission:reports,view`.
4. Angular: tipo `SectionKey` en `core/models/api.models.ts`, ruta con `permissionGuard('reports')` y
   entrada en el menú de `layout/shell.component.ts`. El formulario de perfiles la toma sola de `GET /sections`.

### …cambiar MongoDB por DynamoDB o Firestore
Crear `DynamoProductRepository implements ProductRepository` (y los demás repositorios) en
`Infrastructure/Persistence/` y cambiar las líneas correspondientes en `HexagonalServiceProvider::$singletons`.
Dominio, casos de uso, controladores y frontend **no cambian**. Las pruebas unitarias siguen pasando.

### …cambiar la duración de la sesión o los intentos de login
`.env` → `JWT_ACCESS_TTL` (s), `REFRESH_TTL` (s), `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES` y
`docker compose up -d api`. Valores por defecto en `config/security.php`.

### …hacer obligatorio el 2FA para todos
`.env` → `TWO_FACTOR_REQUIRED=true`. El middleware `account.ready` bloqueará la API (403
`TWO_FACTOR_SETUP_REQUIRED`) hasta que cada usuario lo active en **Seguridad**.

### …enviar correos reales (Gmail, SES, SendGrid)
Variables `MAIL_*` en `docker-compose.yml` (servicio `api`/`worker`). El adaptador usa el Mailer de
Laravel, así que no hay cambios de código. Para otro proveedor sin SMTP: otra clase que implemente
`CredentialsNotifier`.

### …guardar fotos en S3
`S3PhotoStorage implements PhotoStorage` + una línea en `HexagonalServiceProvider`.

### …cambiar el formato de fecha
Frontend: `shared/pipes/app-date.pipe.ts`. PDF/Excel: `TabularFileExporter::cell()`; zona horaria con
`APP_DISPLAY_TIMEZONE`.

### …cambiar colores o tipografía
Tokens `@theme` al inicio de `frontend/src/styles.css` (`--color-brand-*`, `--font-sans`).

### …apuntar el frontend a otra API (otro dominio)
`core/http/api.config.ts` → URL absoluta, y en el backend `CORS_ALLOWED_ORIGINS=https://tu-front`.

### …ver qué pasó con un error que reportó un usuario
El toast muestra `Referencia: <request_id>`; búscalo con `docker compose logs api | grep <request_id>`.

## 3. Comandos útiles

```bash
make test          # todo: Pint + Larastan + PHPUnit + ESLint + Karma + build
make logs          # logs en vivo
make mongo         # consola de MongoDB con el usuario de la app
make shell         # shell en el contenedor de la API
docker compose exec api php artisan route:list --path=api   # todas las rutas
```
