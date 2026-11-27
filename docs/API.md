# API v1 — referencia rápida

Base: `/api/v1` (mismo origen que el frontend). Documentación interactiva: **`/docs/`** (Swagger UI,
fuente `docs/openapi.yaml`). Colección Postman: `docs/postman/`.

## Endpoints

| Método | Ruta | Permiso | Descripción |
|---|---|---|---|
| GET | `/health` | público | Estado de MongoDB y Redis |
| POST | `/auth/login` | público | Login (devuelve JWT o `two_factor_required`) |
| POST | `/auth/two-factor/challenge` | público | Segundo paso con código TOTP o de respaldo |
| POST | `/auth/refresh` | cookie | Renueva sesión (rota refresh token) |
| POST | `/auth/logout` | — | Revoca JWT y refresh |
| POST | `/auth/forgot-password` | público | Valida existencia y envía contraseña temporal |
| GET | `/auth/me` | sesión | Usuario y permisos |
| PUT | `/auth/password` | sesión | Cambiar contraseña (rota sesión) |
| GET | `/auth/two-factor` | sesión | Estado 2FA |
| POST | `/auth/two-factor/setup` | sesión | Semilla + QR |
| POST | `/auth/two-factor/confirm` | sesión | Activa 2FA, devuelve códigos de respaldo |
| DELETE | `/auth/two-factor` | sesión | Desactiva 2FA (contraseña + código) |
| POST | `/auth/two-factor/recovery-codes` | sesión | Regenera códigos de respaldo |
| GET | `/dashboard` | sesión | Contadores y actividad reciente |
| GET | `/products` | products:view | Listado (search, page, per_page, sort, direction) |
| POST | `/products` | products:create | Alta |
| GET | `/products/{id}` | products:view | Visualización |
| PUT | `/products/{id}` | products:update | Edición |
| DELETE | `/products/{id}` | products:delete | Eliminación |
| GET | `/products/export?format=pdf\|xlsx` | products:export | Descarga |
| GET | `/users` | users:view | Listado |
| POST | `/users` (multipart) | users:create | Alta con foto |
| GET | `/users/{id}` | users:view | Detalle (teléfono, foto, perfiles) |
| PUT | `/users/{id}` (JSON) · POST + `_method=PUT` (multipart) | users:update | Edición |
| DELETE | `/users/{id}` | users:delete | Eliminación |
| GET | `/users/export?format=…` | users:export | Descarga |
| GET | `/media/users/{id}/photo?f&exp&sig` | URL firmada | Foto de perfil |
| GET | `/sections` | profiles:view | Catálogo de secciones/acciones |
| GET | `/profiles` | profiles:view | Listado |
| GET | `/profiles/options` | users:view | Lista para selects |
| POST | `/profiles` | profiles:create | Alta |
| GET | `/profiles/{id}` | profiles:view | Detalle (modal) |
| PUT | `/profiles/{id}` | profiles:update | Edición |
| DELETE | `/profiles/{id}` | profiles:delete | Eliminación (409 si está asignado) |
| GET | `/profiles/export?format=…` | profiles:export | Descarga |
| GET | `/audit-logs` | audit:view | Bitácora (filtros `entity_type`, `event`, `entity_id`) |
| GET | `/audit-logs/{id}` | audit:view | Antes vs después |
| GET | `/audit-logs/catalog` | audit:view | Catálogo de filtros |
| GET | `/audit-logs/export?format=…` | audit:export | Descarga |

## Formato de respuestas

```jsonc
// Recurso
{ "data": { ... } }
// Listado
{ "data": [ ... ], "meta": { "page": 1, "per_page": 10, "total": 36, "last_page": 4 } }
// Error
{ "message": "Los datos enviados no son válidos.", "code": "VALIDATION_ERROR",
  "errors": { "price": ["…"] }, "request_id": "8f0c…" }
```

## Códigos de error

| HTTP | `code` | Significado |
|---|---|---|
| 400 | `MALFORMED_INPUT` | Operadores NoSQL (`$ne`, `$gt`…) en la entrada |
| 401 | `UNAUTHENTICATED` · `TOKEN_EXPIRED` · `TOKEN_INVALID` · `TOKEN_REVOKED` | Problema con el JWT |
| 401 | `INVALID_CREDENTIALS` · `ACCOUNT_LOCKED` · `INVALID_TWO_FACTOR_CODE` · `INVALID_PASSWORD` | Login / 2FA |
| 401 | `INVALID_REFRESH_TOKEN` · `REFRESH_TOKEN_REUSED` | Renovación de sesión |
| 403 | `FORBIDDEN` | El perfil no tiene la sección/acción |
| 403 | `PASSWORD_CHANGE_REQUIRED` · `TWO_FACTOR_SETUP_REQUIRED` | Cuenta no lista |
| 404 | `NOT_FOUND` · `USER_NOT_FOUND` | No existe |
| 409 | `EMAIL_TAKEN` · `PROFILE_NAME_TAKEN` · `PROFILE_IN_USE` · `CANNOT_DELETE_SELF` · `PASSWORD_REUSED` | Regla de negocio |
| 422 | `VALIDATION_ERROR` | Campos inválidos (`errors` por campo) |
| 429 | `TOO_MANY_REQUESTS` | Rate limit (header `Retry-After`) |
| 500 | `INTERNAL_ERROR` | Error interno (buscar `request_id` en logs) |

## Ejemplos con curl

```bash
BASE=http://localhost:8080/api/v1
PASS=$(grep ^SEED_ADMIN_PASSWORD= .env | cut -d= -f2-)

# 1) Login → guarda el token y la cookie del refresh
TOKEN=$(curl -s -c cookies.txt -X POST $BASE/auth/login -H 'Content-Type: application/json' \
  -d "{\"email\":\"admin@tap.test\",\"password\":\"$PASS\"}" | python3 -c 'import json,sys;print(json.load(sys.stdin)["data"]["access_token"])')

# 2) Listar productos con búsqueda y orden
curl -s "$BASE/products?search=casco&sort=price&direction=asc&per_page=5" -H "Authorization: Bearer $TOKEN"

# 3) Alta de producto
curl -s -X POST $BASE/products -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Casco clase E","brand":"3M","price":289.90}'

# 4) Exportar a Excel
curl -s -o productos.xlsx "$BASE/products/export?format=xlsx" -H "Authorization: Bearer $TOKEN"

# 5) Alta de usuario con foto
curl -s -X POST $BASE/users -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -F name='Laura Pérez' -F email=laura@tap.test -F phone='+52 314 123 4567' -F photo=@foto.jpg

# 6) Renovar sesión con la cookie
curl -s -b cookies.txt -c cookies.txt -X POST $BASE/auth/refresh -H 'X-Requested-With: XMLHttpRequest'

# 7) Intento de inyección NoSQL → 400
curl -s -X POST $BASE/auth/login -H 'Content-Type: application/json' -d '{"email":{"$ne":null},"password":{"$gt":""}}'
```
