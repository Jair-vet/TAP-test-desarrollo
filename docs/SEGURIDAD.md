# Seguridad

> "Inhackeable" no existe; lo que sí existe es **defensa en profundidad**: varias capas
> independientes, de modo que si una falla, la siguiente contiene el daño. Esto es lo implementado.

## 1. Autenticación

| Control | Detalle | Código |
|---|---|---|
| Contraseñas | **Argon2id** (64 MiB, t=4), rehash automático si cambian los parámetros | `config/hashing.php`, `LaravelPasswordHasher` |
| Política | ≥ 10 caracteres, mayúsculas/minúsculas, número y símbolo; distinta de la actual | `ChangePasswordRequest` + `change-password.page.ts` |
| Contraseñas generadas | CSPRNG (`random_int`), Fisher-Yates seguro, sin caracteres ambiguos | `SecurePasswordGenerator` |
| Fuerza bruta | Rate limit (5/min por correo+IP, 20/min por IP) **y** bloqueo de cuenta 15 min tras 5 fallos | `AppServiceProvider`, `User::registerFailedLogin` |
| Enumeración por tiempo | Si el correo no existe se verifica contra un hash señuelo (mismo costo de CPU) y el mensaje es genérico | `SessionService::login` |
| 2FA | **TOTP RFC 6238** (Google/Microsoft Authenticator, Authy…), semilla de 160 bits **cifrada** (AES-256 + HMAC), QR generado en el servidor, **anti-replay** (no acepta un código del mismo intervalo dos veces), 8 códigos de respaldo de un solo uso (hash SHA-256) | `TwoFactorService`, `Google2faTotpProvider` |
| Desactivar 2FA | Requiere contraseña **y** código vigente | `DisableTwoFactorRequest` |
| Primer ingreso / contraseña temporal | Cambio obligatorio: la API responde 403 `PASSWORD_CHANGE_REQUIRED` en todo excepto `/auth/*` | `EnsureAccountIsReady` |
| Recuperación | Contraseña temporal de 60 min que **no reemplaza** la actual hasta usarse (nadie puede dejar fuera a otro solo conociendo su correo); rate limit 3/15 min | `PasswordService`, `User::promoteTemporaryPassword` |

## 2. Sesión (JWT)

| Control | Detalle |
|---|---|
| Firma | **EdDSA (Ed25519)**, llaves generadas en el contenedor (`storage/jwt`, 0600), nunca en el repo |
| Algoritmo fijado en el servidor | Un token con `alg: none` o `HS256` (confusión de algoritmo) se rechaza — probado en `JwtTokenServiceTest` |
| Claims validados | `iss`, `aud`, `exp`, `nbf`, `iat`, `typ` (un token MFA no sirve como acceso), `jti`, `tv` |
| Vida corta | Access 10 min; renovación proactiva 60 s antes |
| Revocación inmediata | Logout → `jti` en denylist de Redis (TTL = vida restante). Cambio de contraseña/perfiles/2FA → `tokenVersion++` invalida **todos** los tokens del usuario |
| Refresh token | Opaco (384 bits), en BD solo su SHA-256, cookie `HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth` |
| Rotación + detección de reúso | Cada refresh emite uno nuevo; presentar uno ya usado revoca **toda la familia** (`REFRESH_TOKEN_REUSED`) |
| CSRF | SameSite=Strict + header obligatorio `X-Requested-With` en `/auth/refresh` (fuerza preflight si viene de otro origen) |
| Almacenamiento en el navegador | El access token vive **solo en memoria** (nunca localStorage); un XSS no puede robar una sesión persistente |
| Payload | Sin datos sensibles (ni correo ni permisos): un JWT es firmado, no cifrado |

## 3. Autorización

- Middleware `permission:{sección},{acción}` en **cada** ruta (`routes/api.php`). Ocultar botones en Angular
  (`*appCan`) es solo UX; la verificación real es del servidor.
- Permisos efectivos = unión de perfiles, cacheados y **recalculados al instante** cuando cambia un perfil.
- No se puede eliminar el propio usuario ni un perfil asignado (409).

## 4. Inyección (SQL → NoSQL)

MongoDB no usa SQL, pero tiene su equivalente: **inyección de operadores** (`{"$ne": null}`, `{"$gt": ""}`,
`?search[$regex]=.*`), **ReDoS** con expresiones regulares y **inyección de JavaScript** (`$where`).

| Capa | Defensa | Prueba |
|---|---|---|
| 1. Middleware | `RejectNoSqlOperators`: 400 si alguna llave empieza con `$` o contiene bytes nulos (query, body, JSON, anidado) | `RejectNoSqlOperatorsTest`, `AuthApiTest` |
| 2. Validación | FormRequests con tipos estrictos (`string`, `integer`, `Rule::in`, `Rule::enum`): un objeto donde se espera texto → 422 | `AuthApiTest::test_inyeccion_nosql_en_login_se_rechaza` |
| 3. Adaptador Mongo | Filtros construidos como arreglos PHP tipados; IDs validados como ObjectId de 24 hex; la búsqueda **no usa expresiones regulares**: el texto se parte en palabras normalizadas que se comparan por igualdad contra el índice `search_tokens`; orden solo de una **lista blanca** | `ProductApiTest::test_orden_fuera_de_lista_blanca_y_busqueda_con_regex`, `SearchEngineTest` |
| 4. Base de datos | Usuario `tap_app` sin privilegios fuera de su BD; `$jsonSchema` estricto; sin `$where`/JS | migración, `init-app-user.js` |

## 5. Archivos subidos (foto de perfil)

Validación de tipo MIME real + extensión + dimensiones + tamaño (2 MB); la imagen se **decodifica y
re-codifica** a WebP 512×512 con GD (elimina EXIF/GPS, scripts embebidos y archivos políglota); nombre
aleatorio de 128 bits; almacenada **fuera** de la carpeta pública; servida solo con **URL firmada HMAC**
con expiración y `Content-Security-Policy: default-src 'none'`. Rutas validadas contra *path traversal*.

## 6. Cabeceras y transporte

- **CSP estricta** en la SPA: `script-src 'self'` (sin scripts inline ni de terceros; fuentes
  autoalojadas), `frame-ancestors 'none'`, `object-src 'none'`, `base-uri 'self'`.
- `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`,
  `Cross-Origin-Opener-Policy`, **HSTS** cuando se accede por HTTPS (túnel).
- `server_tokens off`, `expose_php = Off`, sin `X-Powered-By`, mitigación *httpoxy*.
- Subresource Integrity (SRI) en los bundles de Angular.
- CORS cerrado (mismo origen).

## 7. Infraestructura y secretos

- `make setup` genera **todos** los secretos aleatorios en `.env` (permisos 600, en `.gitignore`).
- Cada contenedor recibe solo las variables que necesita (la API no conoce la contraseña root de Mongo).
- Mongo y Redis en red `internal` (sin puertos expuestos ni salida a internet); Redis con contraseña.
- Contenedores con `no-new-privileges` y `cap_drop: ALL`; PHP-FPM corre como `www-data`.
- Los correos con contraseñas se encolan **cifrados** (`ShouldBeEncrypted`).
- La bitácora nunca guarda hashes, secretos 2FA ni contraseñas (`User::snapshot`, probado).
- Errores: respuesta genérica + `request_id`; jamás trazas (`APP_DEBUG=false`).

## 8. Laravel 11 y Angular 19 fuera de soporte: avisos abiertos y mitigaciones

Laravel 11 dejó de recibir parches de seguridad en marzo de 2026. `composer audit` reporta avisos
corregidos solo en 12.x/13.x. El examen exige Laravel 11, así que cada uno se mitigó:

| Aviso | Severidad | ¿Aplica? | Mitigación |
|---|---|---|---|
| GHSA-5vg9-5847-vvmq — inyección CRLF en la regla `email` | Alta | Sí (enviamos correos a direcciones del usuario) | `email:strict` + `not_regex` de caracteres de control + Value Object `Email` que rechaza `\r\n` (prueba `EmailTest`) |
| GHSA-crmm-hgp2-wgrp — confusión de rutas en URL firmadas temporales | Media | Evitado | No se usan las signed URLs de Laravel: firma HMAC propia (`MediaUrlSigner`) con llave derivada por HKDF |
| GHSA-jh5r-qr3c-85q8 — XSS en página de depuración | Baja | No | `APP_DEBUG=false` en todos los entornos expuestos |
| GHSA-63hm-g96r-2m2q — inyección en `Concurrency::run` | Baja | No | No se usa el facade `Concurrency` |

> `composer audit` muestra **5** entradas porque el aviso de CRLF en la regla `email` aparece dos veces
> (una desde la base de avisos de Packagist, `PKSA-3r5d-mb8f-1qw9`, y otra desde GitHub, `CVE-2026-48019`).
> Son los mismos 4 problemas de la tabla.

**Angular 19** (también exigido por el examen) tiene 11 avisos que solo se corrigen en Angular 20+. Se revisó
cada uno contra el código y **ninguno aplica** a esta app:

| Avisos | Requieren | En esta app |
|---|---|---|
| GHSA-39pv-4j6c-2g6v, GHSA-jhpw-976m-542j, GHSA-p297-fm68-3q8c, GHSA-rgjc-h3x7-9mwg, GHSA-ff3f-86qr-9cv3 | SSR, hidratación o `HttpTransferCache` | Es una SPA estática: no hay SSR |
| GHSA-jj27-h5hq-8x99 | i18n (`$localize`, atributos `i18n`) | No se usa i18n |
| GHSA-58w9-8g37-x9v5 | two-way binding a propiedades sensibles | El único `[( )]` es `[(value)]` de un componente propio |
| GHSA-hh8m-fm6v-7cvg | host bindings a `href`/`src`/HTML | Solo hay bindings de estilo y `aria-*` |
| GHSA-48r7-hpm6-gfxm | `formatDate` con formatos que controla el usuario | Formatos fijos (`dd/MM/yyyy HH:mm`) |
| GHSA-jfgx-762w-99vr | cookie `XSRF-TOKEN` | El backend nunca la emite (CSRF: SameSite=Strict + `X-Requested-With` + Bearer en memoria) |
| GHSA-62vg-58rm-qff7 | outlets con nombre en el router | No hay outlets con nombre |

**Cómo se controla en el CI (job `security`, `make audit`).** Las excepciones están escritas con su razón en
`backend/composer.json` (`config.audit.ignore`) y en `frontend/audit-ci.jsonc` (`audit-ci` 7.1.0). Cualquier
aviso **nuevo** (moderate o mayor) hace fallar el pipeline, así que no se puede colar una dependencia vulnerable
sin revisarla.

**Recomendación** fuera del examen: migrar a Laravel 12/13 (PHP ≥ 8.3) y Angular ≥ 20; la arquitectura
hexagonal aísla el framework, así que el cambio afecta sobre todo a la capa de infraestructura.

## 9. Mapeo OWASP Top 10 (2021)

| Riesgo | Cobertura |
|---|---|
| A01 Control de acceso roto | Permisos por sección/acción en servidor, `tokenVersion`, guards |
| A02 Fallas criptográficas | Argon2id, EdDSA, AES-256 para secretos 2FA, HTTPS por túnel, HSTS |
| A03 Inyección | Capas 1-4 de la sección 4, CSP contra XSS, Angular escapa por defecto |
| A04 Diseño inseguro | Contraseña temporal no destructiva, detección de reúso de refresh, bloqueo de cuenta |
| A05 Configuración insegura | Debug apagado, cabeceras, red interna, secretos aleatorios, CORS cerrado |
| A06 Componentes vulnerables | `composer audit` + `audit-ci` obligatorios en CI (fallan con avisos nuevos), versiones fijadas, excepciones revisadas y documentadas (§8) |
| A07 Fallas de autenticación | 2FA, rate limit, bloqueo, política de contraseñas, sesiones cortas |
| A08 Integridad | Acciones de CI fijadas por SHA, SRI, `composer.lock`/`package-lock.json` |
| A09 Registro y monitoreo | Bitácora de logins/fallos/bloqueos/reúso, `X-Request-Id` en logs |
| A10 SSRF | DomPDF con `isRemoteEnabled=false`, `allow_url_fopen=Off` en producción |
