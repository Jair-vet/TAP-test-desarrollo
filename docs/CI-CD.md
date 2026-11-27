# CI/CD

Criterio del examen: **"Extras — Implementación de CI/CD, pruebas unitarias" (10 %)**.

## 1. Pipeline

```mermaid
flowchart LR
    P[push / pull request] --> B[backend<br/>Pint PSR-12 · Larastan · PHPUnit]
    P --> F[frontend<br/>ESLint · Jasmine/Karma · build]
    B --> D[docker<br/>construye imágenes]
    F --> D
    B --> E[e2e<br/>stack completo · Postman/Newman<br/>k6 · Lighthouse]
    F --> E
    P --> S[security<br/>composer audit · audit-ci]
    D & E & S -->|verde en main| CD[CD<br/>build nativo amd64 + arm64<br/>→ ghcr.io]
    T[tag v*] --> CD --> R[GitHub Release<br/>openapi.yaml + Postman]
```

| Workflow | Archivo | Cuándo | Qué hace |
|---|---|---|---|
| **CI** | `.github/workflows/ci.yml` | cada push y PR | 5 jobs: `backend`, `frontend`, `docker`, `e2e`, `security` |
| **CD** | `.github/workflows/cd.yml` | CI en verde sobre `main`, o tag `v*` | Imágenes `api` y `web` para amd64 y arm64, cada una en un **runner nativo** (sin emulación), con SBOM y procedencia; se unen en una imagen multi-arquitectura → `ghcr.io/<dueño>/tap-prueba-{api,web}`; con tag, crea el Release |

Detalles de calidad:
- Acciones **fijadas por SHA** (no por tag): evita ataques de cadena de suministro.
- `permissions` mínimos por workflow; `concurrency` cancela corridas viejas del mismo branch.
- Caché de dependencias (npm) y de capas Docker (GitHub Actions cache).
- El job `e2e` usa **los mismos scripts que en local** (`scripts/setup.sh`, `scripts/test-api.sh` y
  `scripts/test-perf.sh`) y sube los reportes (Newman, k6, Lighthouse) como artefacto.
- El job `security` es **obligatorio**: falla con cualquier aviso nuevo; los conocidos de Laravel 11 / Angular 19
  están revisados y justificados (`docs/SEGURIDAD.md` §8).
- La CD solo publica si el CI pasó: nunca se publica una imagen sin pruebas.

## 2. Mismo pipeline en tu equipo (solo Docker)

| Comando | Sin `make` | Equivale a | Qué corre |
|---|---|---|---|
| `make test-back` | `./scripts/test-back.sh` | job `backend` | Pint (PSR-12) + Larastan nivel 6 + PHPUnit (unitarias + API contra MongoDB, BD aislada `tap_test`) |
| `make test-front` | `./scripts/test-front.sh` | job `frontend` | ESLint (angular-eslint) + Karma/Jasmine en Chromium headless + build de producción |
| `make test-api` | `./scripts/test-api.sh` | job `e2e` | Colección de Postman contra el sistema levantado (Newman); reporte JUnit en `reports/newman.xml` |
| `make test-perf` | `./scripts/test-perf.sh` | job `e2e` | Presupuestos de rendimiento: k6 (errores < 1 %, p95 < 300 ms) + Lighthouse (≥ 90, Web Vitals) |
| `make audit` | `./scripts/test-security.sh` | job `security` | `composer audit` + `audit-ci` |
| `make test` | `test-back` + `test-front` + `test-api` | — | Todas las pruebas |
| `make ci` | — | CI completo | Construye imágenes, levanta el stack y corre todo lo anterior |

Ninguno requiere instalar PHP, Composer, Node ni MongoDB: todo corre en contenedores con versiones
fijas (`php:8.2.36-fpm-alpine3.23`, `node:22.23.5-alpine3.23`, `postman/newman:6.1.3-alpine`,
`grafana/k6:1.8.3`, `@lhci/cli@0.16.1`, `audit-ci@7.1.0`).
Los scripts nunca reciben contraseñas como argumentos (no aparecen en `ps` ni en los logs del CI).

## 3. Pruebas

| Capa | Herramienta | Dónde | Qué cubren |
|---|---|---|---|
| Dominio y casos de uso (backend) | PHPUnit | `backend/tests/Unit` | Reglas de precio, correo (CRLF), códigos, permisos por perfil, bloqueo de cuenta, contraseña temporal, JWT (firma, `alg:none`, expiración, tipo), bitácora (diferencias), anti-inyección NoSQL. `ProductServiceTest` corre **sin base de datos** (adaptadores en memoria) |
| API HTTP (backend) | PHPUnit + MongoDB real | `backend/tests/Feature` | Login, bloqueo, logout/revocación, refresh con rotación y detección de reúso, recuperación de contraseña, CRUD, validaciones del examen, permisos 403, exportación PDF/Excel, alta de usuario con foto |
| Frontend | Jasmine/Karma | `frontend/src/**/*.spec.ts` | Store de sesión y `AuthService` (login, 2FA, un solo refresh con cabecera anti-CSRF, logout), guards por perfil, `*appCan`, interceptores (Bearer, refresh, errores), `ListController` de las tablas (debounce, orden, filtros, errores), validadores, pipes de fecha/teléfono, paginador, notificaciones y bloqueo de redirecciones abiertas en la bandeja |
| Escalabilidad (backend) | PHPUnit + profiler de MongoDB | `backend/tests/Feature/QueryPlanTest.php`, `RequestMetricsTest.php`, `RateLimitTest.php` | Ninguna consulta hace COLLSCAN ni ordena en memoria; ≤ 15 consultas por petición y sin N+1; límite de peticiones por usuario |
| Buscador | PHPUnit | `backend/tests/Unit/Shared/SearchEngineTest.php`, `ProductApiTest` | Prefijos, acentos, varias palabras, código, límites y entradas maliciosas |
| Contrato de la API (extremo a extremo) | Postman + Newman | `docs/postman/` | 44 peticiones con 87 aserciones: código HTTP de cada endpoint, formato paginado, archivos PDF/Excel, bitácora antes/después, y casos negativos del examen (precio de 4 dígitos, alta sin foto, correo y perfil duplicados → 409, teléfono sin código de país, sin token → 401, registro eliminado → 404, inyección NoSQL → 400) |
| Rendimiento de la API | k6 | `perf/k6/api-load.js` | 4 perfiles navegando a la vez 30 s: errores < 1 %, p95 < 300 ms, p99 < 800 ms |
| Rendimiento del frontend | Lighthouse CI | `perf/lighthouse/lighthouserc.json` | Rendimiento, accesibilidad y buenas prácticas ≥ 90; FCP, LCP, CLS y TBT dentro de presupuesto |

## 4. Activarlo en GitHub
1. Crear el repositorio y subir la rama (requiere autorización del dueño del repo).
2. Las acciones corren solas; las imágenes quedan en *Packages* del perfil/organización.
3. Para un release: `git tag v1.0.0 && git push --tags`.
