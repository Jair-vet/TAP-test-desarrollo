# Modelo de datos NoSQL (MongoDB 8)

> Criterio del examen: **"Uso correcto de la BD — implementación adecuada del modelo NoSQL" (15 %)**.
> Aquí está cada colección con un documento **real** del sistema (secretos ocultos), por qué se modeló
> así, sus índices y cómo se protege su integridad.

## 1. Principios aplicados

| Principio NoSQL | Cómo se aplica |
|---|---|
| **Modelar según cómo se lee** | Cada pantalla se resuelve con 1 consulta: el perfil trae sus secciones embebidas; el listado de usuarios no necesita perfiles |
| **Embeber lo que pertenece al documento** | `profiles.sections[]`, `users.security{}`, `users.two_factor{}`, `audit_logs.before/after/changes` |
| **Referenciar lo que se comparte** | `users.profile_ids[]` → `profiles._id` (un perfil lo usan muchos usuarios; si se edita, no hay que tocar a cada usuario) |
| **Tipos BSON correctos** | `Decimal128` para dinero (sin errores de punto flotante), `Date` (UTC) para fechas, `ObjectId` para referencias |
| **Esquema flexible donde aporta** | La bitácora guarda snapshots de entidades distintas (producto, usuario, perfil) en una sola colección |
| **Esquema estricto donde importa** | Validadores `$jsonSchema` con `validationLevel: strict` en todas las colecciones de negocio |
| **Índices para cada patrón de consulta** | Únicos (código, correo, nombre de perfil), uno por cada orden de cada tabla (`{campo, _id}`), multikey en `profile_ids` y en el buscador (`search_tokens`), TTL en sesiones. Regla automática: **0 COLLSCAN** (`QueryPlanTest`) |
| **Atomicidad sin transacciones** | Códigos consecutivos con `findOneAndUpdate` + `$inc` (operación atómica de un documento) |
| **Mínimo privilegio** | La API usa el usuario `tap_app` (`readWrite` + `dbAdmin` solo sobre la BD `tap`), nunca root |

Código: adaptadores en `backend/src/*/Infrastructure/Persistence/Mongo*Repository.php`, utilidades en
`backend/src/Shared/Infrastructure/Persistence/Mongo/`, estructura en
`backend/database/migrations/2026_11_25_000001_create_mongo_collections.php`.

## 2. Colecciones

### `products`
```json
{
  "_id": "6b073507e380702afe065cca",
  "code": "PRD-000001",
  "name": "Casco de seguridad clase E",
  "brand": "3M",
  "price": { "$numberDecimal": "289.90" },
  "search_tokens": ["p", "pr", "prd", "0", "00", "…", "000001", "c", "ca", "cas", "casc", "casco", "…", "3", "3m"],
  "created_at": "2026-11-15T07:20:38.542Z",
  "updated_at": "2026-11-15T07:20:38.542Z"
}
```
- `price` en **Decimal128**: 999.99 se guarda exacto; se ordena numéricamente.
- `search_tokens`: **buscador**. Prefijos normalizados (minúsculas, **sin acentos**) de código, nombre y marca, con
  índice multikey: buscar "dielec" encuentra "dieléctricas" con un `IXSCAN`. Ver `docs/RENDIMIENTO.md` §4.
- Validador: `code` con patrón `^PRD-[0-9]{6,}$`, `name`/`brand` obligatorios con longitud, `price` tipo `decimal`.
- Índices: `code` **único**, `{created_at:-1, _id:-1}`, `{name, _id}`, `{price, _id}`, `{brand, _id}` (uno por columna
  ordenable, igual al orden real) y `search_tokens` (buscador).

### `users`
```json
{
  "_id": "6b073506e380702afe065cc6",
  "code": "USR-000001",
  "name": "Administrador TAP",
  "email": "admin@tap.test",
  "phone": "+523141234567",
  "photo_path": "photos/10/101685240e82ddf94606172cf6f45714.webp",
  "profile_ids": ["6b073506e380702afe065cc2"],
  "security": {
    "password_hash": "<argon2id>",
    "must_change_password": false,
    "temporary_password": null,
    "token_version": 4,
    "failed_login_attempts": 0,
    "locked_until": null,
    "last_login_at": "2026-11-27T16:48:14.389Z"
  },
  "two_factor": { "enabled": false, "secret": null, "recovery_codes": [], "last_used_step": null },
  "created_at": "2026-11-25T19:20:38.542Z",
  "updated_at": "2026-11-25T19:45:33.501Z"
}
```
- `profile_ids`: **referencias** (ObjectId) con índice **multikey** → "¿cuántos usuarios tienen este perfil?"
  es una consulta indexada (se usa para impedir borrar un perfil en uso).
- `security` y `two_factor`: **embebidos** (se leen siempre con el usuario y no se comparten).
- `phone` en formato **E.164** (validado por patrón también en el `$jsonSchema`).
- Los **listados usan proyección** que excluye `security` y `two_factor`: los hashes no salen de Mongo si no
  hacen falta.
- Índices: `email` **único**, `code` **único**, `profile_ids`, `{created_at:-1,_id:-1}`, `{name, _id}`, `search_tokens`.

### `profiles`
```json
{
  "_id": "6b073506e380702afe065cc2",
  "code": "PRF-000001",
  "name": "Administrador",
  "name_key": "administrador",
  "description": "Acceso total al sistema.",
  "sections": [
    { "section": "products", "actions": ["view", "create", "update", "delete", "export"] },
    { "section": "audit", "actions": ["view", "export"] }
  ],
  "created_at": "2026-11-25T19:20:38.542Z"
}
```
- `sections[]` **embebido**: el detalle del perfil y el cálculo de permisos son una sola lectura.
- `name_key` (nombre en minúsculas) con índice **único** → "Almacén" y "almacén" no pueden coexistir; también
  sirve para ordenar por nombre sin importar mayúsculas. Buscador: `search_tokens` (código y nombre).
- Validador: `sections` con `minItems: 1`, `section` y `actions` restringidos con `enum`.

### `audit_logs` (bitácora, solo inserción)
```json
{
  "event": "updated",
  "entity_type": "profile",
  "entity_code": "PRF-000007",
  "actor": { "id": "6b07…", "code": "USR-000001", "name": "Administrador TAP", "email": "admin@tap.test" },
  "before": { "name": "Auditor externo", "description": "Solo lectura", "sections": { "products": "view, export", "audit": "view" } },
  "after":  { "name": "Auditor externo", "description": "", "sections": { "audit": "view, export" } },
  "changes": [
    { "field": "description", "before": "Solo lectura", "after": "" },
    { "field": "sections.audit", "before": "view", "after": "view, export" }
  ],
  "ip": "172.24.0.1",
  "created_at": "2026-11-25T19:56:29.505Z"
}
```
- **"Información anterior vs actual"** del examen: `before`/`after` son snapshots completos y `changes` ya
  trae la diferencia campo por campo (calculada por `Audit/Domain/ChangeSet.php`).
- `actor` **embebido** (copia): la bitácora debe mostrar quién lo hizo aunque ese usuario se borre después.
- Índices: `{created_at:-1,_id:-1}`, `{entity_type, entity_id, created_at:-1}` (historial de un registro),
  `{event, created_at:-1}` (filtros), `{event, _id}` y `{entity_type, _id}` (orden) y `search_tokens` (buscador).

### `refresh_tokens`
```json
{
  "user_id": "6b073506e380702afe065cc6",
  "family_id": "6a076cdb290c2e1309d9fffe4af616b3",
  "token_hash": "<sha256>",
  "expires_at": "2026-12-02T19:21:16.328Z",
  "revoked_at": "2026-11-25T19:21:20.563Z",
  "replaced_by": "6b0735302c5f5af83302937a"
}
```
- Solo se guarda el **SHA-256** del token. `family_id` + `replaced_by` permiten detectar reúso.
- Índice **TTL** en `expires_at`: MongoDB borra solo los vencidos (limpieza sin cron). `token_hash` **único**.

### `counters`
```json
{ "_id": "profiles", "seq": 7 }
```
- `findOneAndUpdate({_id}, {$inc: {seq: 1}}, {upsert: true, returnDocument: 'after'})` es **atómico**:
  dos altas simultáneas nunca obtienen el mismo código (contar documentos + 1 sí fallaría).

## 3. Patrones de consulta y su índice

| Pantalla / operación | Consulta | Índice que la sirve |
|---|---|---|
| Tabla de productos ordenada por fecha | `find().sort({created_at:-1,_id:-1}).skip().limit()` | `created_desc` |
| Ordenar por precio/nombre/marca | `sort({price:1,_id:1})` | `price_sort` / `name_sort` / `brand_sort` |
| Ordenar por código o correo (únicos) | `sort({code:1})` | `uniq_code` / `uniq_email` |
| Buscar "casco 3m" | `find({search_tokens: {$all: ["casco","3m"]}})` + `countDocuments` igual | `search_tokens` (multikey) |
| Ver/editar por código | `findOne({code})` | `uniq_code` |
| Login | `findOne({email})` | `uniq_email` |
| ¿Perfil en uso? | `countDocuments({profile_ids: id})` | `profile_ids` (multikey) |
| Historial de un registro | `find({entity_type, entity_id}).sort({created_at:-1})` | `entity_timeline` |
| Renovar sesión | `findOne({token_hash})` | `uniq_hash` |
| Total sin filtros | `estimatedDocumentCount()` (metadatos, O(1)) | — |

## 4. Integridad y seguridad de los datos
- **Validación en 3 capas:** FormRequest (HTTP) → Value Objects del dominio (`Price`, `Email`,
  `PhoneNumber`, `EntityCode`) → `$jsonSchema` en MongoDB.
- **Inyección NoSQL bloqueada** (operadores `$ne`, `$gt`, `$regex` en la entrada): ver `docs/SEGURIDAD.md` §4.
- Escrituras con `w: majority` y `retryWrites: true` (`config/database.php`).

## 5. Si el sistema crece
- Búsqueda con tolerancia a errores de dedo o relevancia: Atlas Search o Meilisearch (cambio solo en el adaptador Mongo).
- Paginación por cursor (`_id > último`) en lugar de `skip` para millones de filas.
- Bitácora: colección *time series* o TTL de retención (p. ej. 1 año) y *sharding* por `entity_type`.
- Cambiar a DynamoDB/Firestore: implementar los mismos puertos (`ProductRepository`, etc.); el dominio no cambia.
