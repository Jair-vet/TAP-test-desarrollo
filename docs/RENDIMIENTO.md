# Rendimiento y escalabilidad

Todo lo de esta guía está **automatizado**: si una regla o un presupuesto se rompe, falla la prueba
local y falla el CI. No depende de que alguien "se acuerde" de revisarlo.

## 1. Reglas y presupuestos (qué se mide y dónde falla)

| # | Regla / métrica | Umbral | Cómo se verifica | Comando · job del CI |
|---|---|---|---|---|
| 1 | **Ninguna consulta recorre una colección completa** (COLLSCAN) ni ordena un listado en memoria | 0 consultas | `tests/Feature/QueryPlanTest.php`: enciende el profiler de MongoDB, recorre todas las pantallas, órdenes, búsquedas y filtros, y revisa el plan real de cada consulta | `make test-back` · `backend` |
| 2 | **Consultas acotadas por petición** y sin N+1 | ≤ 15 por petición; pedir 5 o 100 filas cuesta las mismas consultas | `tests/Feature/RequestMetricsTest.php` (lee la cabecera `Server-Timing`) | `make test-back` · `backend` |
| 3 | **Límite de peticiones por usuario**, no por IP | 120/min por usuario | `tests/Feature/RateLimitTest.php` | `make test-back` · `backend` |
| 4 | **API bajo carga** (4 perfiles navegando a la vez, 30 s) | errores < 1 % · p95 < 300 ms · p99 < 800 ms | k6: `perf/k6/api-load.js` | `make test-perf` · `e2e` |
| 5 | **Frontend** (Lighthouse, escritorio) | rendimiento, accesibilidad y buenas prácticas ≥ 90 · FCP < 1.8 s · LCP < 2.5 s · CLS < 0.1 · TBT < 200 ms | Lighthouse CI: `perf/lighthouse/lighthouserc.json` | `make test-perf` · `e2e` |
| 6 | **Peso del bundle** de Angular | inicial: aviso 700 kB / error 1 MB · estilos por componente: aviso 6 kB / error 12 kB | `budgets` en `frontend/angular.json` (los aplica `ng build`) | `make test-front` · `frontend` |
| 7 | **Dependencias sin avisos nuevos** | 0 avisos sin revisar | `composer audit` + `audit-ci` | `make audit` · `security` |

## 2. Resultados actuales (27-nov-2026, Docker en una Mac)

| Métrica | Resultado | Presupuesto |
|---|---|---|
| k6: peticiones / errores | 185 / **0 %** | < 1 % |
| k6: latencia de lecturas p95 / p99 | **12.8 ms / 16.2 ms** | 300 ms / 800 ms |
| Lighthouse (login): rendimiento · accesibilidad · buenas prácticas | **100 · 100 · 96** | ≥ 90 |
| Lighthouse: FCP · LCP · TBT · CLS | **0.2 s · 0.2 s · 0 ms · 0** | 1.8 s · 2.5 s · 200 ms · 0.1 |
| Bundle inicial | 660 kB (128 kB comprimido) | 700 kB aviso / 1 MB error |
| Consultas con COLLSCAN | **0** (antes de esta regla había 26: ver §6) | 0 |

Los reportes completos quedan en `reports/` (`k6-summary.json`, `lighthouse/*.html`) y el CI los sube como
artefacto `reportes-e2e-y-rendimiento`.

## 3. Métricas en tiempo real

Cada respuesta de la API trae la cabecera estándar **`Server-Timing`** (middleware `RecordRequestMetrics`):

```
Server-Timing: app;dur=18.4;desc="Laravel", db;dur=3.1;desc="MongoDB: 2 consulta(s)"
```

- En el navegador: DevTools → Network → una petición → pestaña **Timing** (aparece desglosado).
- `db` se mide con la API de monitoreo del driver oficial de MongoDB (`MongoCommandMetrics`): cuenta y cronometra
  **todas** las consultas sin tocar los repositorios.
- Advertencias en el log (`make logs`):
  - `Petición lenta` si tarda más de `PERF_SLOW_REQUEST_MS` (500 ms por defecto).
  - `Demasiadas consultas en una petición (posible N+1)` si pasa de `PERF_MAX_QUERIES` (15).
- Configuración: `backend/config/performance.php` (variables `PERF_*`).

## 4. Buscador

Las tablas de productos, usuarios, perfiles y bitácora usan un **motor de búsqueda propio sobre MongoDB**: un
índice invertido de prefijos.

**Cómo funciona.** Al guardar un documento se calculan los prefijos de cada palabra de sus campos buscables,
normalizados (minúsculas, sin acentos), y se guardan en `search_tokens`, que tiene un índice multikey:

```
"PRD-000038 · Botas dieléctricas · Truper"
  → search_tokens: [p, pr, prd, 0, 00, …, 000038, b, bo, bot, bota, botas, d, di, …, dielectricas, t, tr, …, truper]
```

Buscar es una consulta por igualdad sobre ese índice:

| Escribes | Consulta | Encuentra |
|---|---|---|
| `dielec` | `{ search_tokens: "dielec" }` | "Botas dieléctricas" (sin acento, por prefijo) |
| `casco 3m` | `{ search_tokens: { $all: ["casco", "3m"] } }` | Productos que tienen **las dos** palabras |
| `PRD-000038` | `{ search_tokens: { $all: ["prd", "000038"] } }` | Por código |
| `.*(a+)+$` | `{ search_tokens: "a" }` | Nada raro: no hay expresiones regulares |

**Por qué así.**
- **Rápido a escala:** es un `IXSCAN`. El tiempo depende de cuántos documentos coinciden, no de cuántos hay. La
  versión anterior (`regex` sobre un texto) recorría la colección completa en cada búsqueda y en cada conteo.
- **Seguro:** el texto del usuario nunca se convierte en expresión regular (sin ReDoS, sin comodines, sin operadores).
- **Consistente:** el índice se actualiza en la misma escritura del documento; no hay un segundo sistema que
  sincronizar ni resultados "atrasados".
- **Acotado:** máximo 20 caracteres por prefijo, 400 prefijos por documento y 8 palabras por búsqueda.

**Límites conscientes.** Busca por **inicio de palabra** (como los buscadores de e-commerce): "asco" no
encuentra "casco". No corrige errores de dedo. Si el negocio lo pidiera, el reemplazo natural es **MongoDB Atlas
Search** o **Meilisearch**: por la arquitectura hexagonal solo cambiaría el adaptador
(`Mongo::searchFilter()` / `searchTokens()` en `backend/src/Shared/Infrastructure/Persistence/Mongo/Mongo.php`).

Migración: `backend/database/migrations/2026_11_27_000001_search_engine_and_scalable_indexes.php` (convierte los
documentos existentes en lotes de 500).

## 5. Lo que ya cuida el rendimiento en cada capa

| Capa | Técnica | Dónde |
|---|---|---|
| Base de datos | Un índice por patrón de consulta, incluido cada orden de cada tabla (`{campo, _id}`) | migraciones, `docs/MODELO-DATOS.md` |
| Base de datos | Conteo sin filtros con `estimatedDocumentCount()` (metadatos, O(1)) | repositorios Mongo |
| Base de datos | Proyecciones: los listados no traen campos pesados (snapshots de bitácora, tokens, seguridad) | repositorios Mongo |
| API | Paginación obligatoria con `per_page` de 5 a 100 (nunca "traer todo") | `ListRequest` |
| API | Caché versionada en Redis (invalidación O(1) al escribir) + `ETag`/`304` | `VersionedQueryCache`, `HttpCacheHeaders` |
| API | Exportaciones PDF/Excel en *streaming* con cursor por lotes de 500 | exportadores, repositorios (`cursor()`) |
| API | Correos en cola (los envía el `worker`, la petición no espera al SMTP) | `CredentialsMail` (`ShouldQueue`) |
| PHP | OPcache + JIT (`tracing`), sin revalidar archivos en producción | `docker/php/conf.d/app.ini` |
| Web | gzip, archivos con hash cacheados 1 año (`immutable`) | `docker/nginx/default.conf` |
| Angular | Rutas con carga diferida (lazy), `OnPush` + signals, búsqueda con debounce de 300 ms y cancelación de peticiones viejas (`switchMap`) | `app.routes.ts`, `ListController` |

## 6. Problemas reales que encontraron estas reglas

| Hallazgo | Impacto a escala | Corrección |
|---|---|---|
| La búsqueda usaba `regex` sobre un texto (y `countDocuments` con esa regex) | COLLSCAN en cada búsqueda: crece lineal con los datos | Motor de búsqueda por prefijos con índice multikey (§4) |
| Ordenar por precio, nombre, marca, correo, código… usaba índices de un solo campo, pero el orden real es `{campo, _id}` | COLLSCAN + ordenamiento en memoria (MongoDB aborta a los 100 MB) | Índices compuestos `{campo, _id}`; los campos únicos se ordenan con su índice único |
| `throttle:api` corría **antes** de la autenticación JWT (Laravel reordena middlewares por prioridad) | El límite de 120/min se aplicaba por IP: toda una oficina compartía el cupo | `AuthenticateJwt` implementa `AuthenticatesRequests` → corre antes del límite; prueba `RateLimitTest` |

## 7. Cómo escalar a futuro

1. **Horizontal en la API.** La API no guarda estado (JWT + Redis), así que se pueden poner N réplicas de `api`
   detrás de Nginx o de un balanceador sin cambiar código.
2. **Redis compartido.** Caché, lista de tokens revocados y límites de peticiones ya viven en Redis, así que
   funcionan igual con varias réplicas.
3. **MongoDB:**
   - *Replica set* para alta disponibilidad y lecturas en secundarios.
   - Si la bitácora crece mucho: *sharding* por `{ entity_type, created_at }` o un TTL de retención.
4. **Workers.** Se escalan aparte (`docker compose up --scale worker=3`) si crecen los correos u otros trabajos.
5. **Estáticos.** El frontend son archivos con hash: se pueden servir desde un CDN sin cambios.
6. **Buscador.** Si se necesita tolerancia a errores o relevancia, se cambia el adaptador por Atlas Search o
   Meilisearch (§4).
7. **Observabilidad.** `Server-Timing` y los logs con `request_id` se pueden enviar a Grafana/Loki u
   OpenTelemetry sin tocar la lógica.
