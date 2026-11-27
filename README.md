# Prueba técnica · Área de Desarrollo — Sistema de Gestión TAP

Sistema de **productos, usuarios, perfiles y bitácora** con autenticación JWT + 2FA, construido con
**Laravel 11 / PHP 8.2**, **Angular 19 / TypeScript 5.8** y **MongoDB 8**, empaquetado en Docker.

> **Lo único que necesitas instalar es Docker.** PHP, Composer, Node, MongoDB, Redis, Nginx, el
> servidor de correo de prueba y hasta las herramientas de pruebas (PHPUnit, Karma, Newman) corren
> dentro de contenedores con versiones fijas.

---

## 1. Requisitos

| Sistema | Qué instalar |
|---|---|
| macOS | [Docker Desktop](https://www.docker.com/products/docker-desktop/) (trae `docker compose`). `make` ya viene con las herramientas de línea de comandos |
| Linux | Docker Engine + plugin Compose v2. `make` con el gestor de paquetes (`sudo apt install make`) |
| Windows | Docker Desktop con **WSL 2**. Abre una terminal de Ubuntu (WSL) y sigue los mismos pasos; o usa Git Bash con los scripts de `scripts/` en lugar de `make` |

- Puerto libre: **8080** (se cambia con `WEB_PORT` en `.env`).
- Memoria para Docker: 4 GB o más (el build de Angular y las pruebas del frontend usan ~3 GB).
- `make` es opcional: cada comando tiene su script equivalente (tabla de la sección 4).

---

## 2. Levantar el sistema (paso a paso)

```bash
# 1) Entra a la carpeta del proyecto
cd prueba-de-desarrollo

# 2) Primera vez: genera .env con secretos aleatorios, construye las imágenes y levanta todo
make setup                 # sin make: ./scripts/setup.sh      (3-5 min la primera vez)

# 3) Muestra las URLs, los usuarios demo y sus contraseñas
make credentials           # sin make: ./scripts/credentials.sh
```

Cuando termina verás los 6 contenedores en verde (`make ps`: web, api, worker, mongo, redis, mailpit). Abre:

| Qué | URL |
|---|---|
| **Sistema** (Angular) | http://localhost:8080 |
| **Swagger UI** (documentación interactiva de la API) | http://localhost:8080/docs/ |
| **Bandeja de correos de prueba** (Mailpit) | http://localhost:8080/mail/ → pide acceso en `/correos` (usuario `revisor` y `MAILPIT_UI_PASSWORD` del `.env`; un administrador entra con su sesión desde el menú *Correos (demo)*) |
| Estado del servicio | http://localhost:8080/api/v1/health |
| Colección de Postman | `docs/postman/TAP-Prueba.postman_collection.json` + entorno `TAP-Local.postman_environment.json` |

**Usuarios demo** (las contraseñas se generan al azar en tu `.env`: `SEED_ADMIN_PASSWORD` para el
administrador y `SEED_DEMO_PASSWORD` para los demás; `make credentials` las muestra):

| Correo (usuario) | Perfil | Puede |
|---|---|---|
| `admin@tap.test` | Administrador | Todo |
| `almacen@tap.test` | Almacén | Productos completo |
| `consulta@tap.test` | Consulta | Solo ver productos y usuarios |
| `rh@tap.test` | Recursos Humanos | Usuarios completo + ver perfiles |

Para apagar: `make down` (conserva los datos). Para empezar de cero: `make reset` (⚠ borra los datos).

---

## 3. Cómo revisar cada parte

| Quiero revisar… | Comando o lugar | Qué debe pasar |
|---|---|---|
| Todo el pipeline, como en GitHub Actions | `make ci` | Construye imágenes, levanta el stack y corre backend + frontend + Postman. Termina con `✔ CI local completo` |
| Backend: PSR-12 + análisis estático + pruebas | `make test-back` | Pint sin cambios, Larastan `[OK] No errors`, PHPUnit **64 pruebas (460 aserciones) en verde** |
| Frontend: convenciones Angular + pruebas + build | `make test-front` | ESLint `All files pass linting`, Karma **54 pruebas en verde**, build de producción |
| API documentada en Postman (automático) | `make test-api` | Newman corre la colección: **44 peticiones, 87 aserciones, 0 fallos**; reporte en `reports/newman.xml` |
| API en Postman (a mano) | Importa la colección y el entorno de `docs/postman/`, escribe `password` en el entorno y ejecuta *Sesión → Login* | Cada petición trae ejemplo de respuesta y su prueba en la pestaña *Tests* |
| API en Swagger | http://localhost:8080/docs/ → *Authorize* con el token del login | Cada endpoint lista sus respuestas posibles (200/201/204/400/401/403/404/409/422/429) con ejemplos |
| Modelo NoSQL | `make mongo` y luego `db.getCollectionInfos()`, `db.products.getIndexes()` | Colecciones con validador `$jsonSchema` e índices únicos/TTL; ver [docs/MODELO-DATOS.md](docs/MODELO-DATOS.md) |
| Correos (alta de usuario y recuperación) | http://localhost:8080/mail/ | Llegan las credenciales del usuario nuevo y la contraseña temporal de recuperación |
| Bitácora | Menú *Bitácora* → abre un registro | Información anterior vs actual, campo por campo |

Guion de la demostración: [docs/DEMO.md](docs/DEMO.md).

---

## 4. Comandos

| `make …` | Sin `make` | Qué hace |
|---|---|---|
| `make setup` | `./scripts/setup.sh` | Primera vez: `.env` con secretos aleatorios + imágenes + stack arriba |
| `make up` / `make down` | `docker compose up -d --wait` / `docker compose down` | Levanta / detiene (conserva datos) |
| `make restart` | `docker compose build && docker compose up -d --wait` | Reconstruye tras cambiar código |
| `make ps` / `make logs` | `docker compose ps` / `docker compose logs -f api worker web` | Estado / logs en vivo |
| `make credentials` | `./scripts/credentials.sh` | URLs y usuarios demo |
| `make test-back` | `./scripts/test-back.sh` | Pint (PSR-12) + Larastan nivel 6 + PHPUnit |
| `make test-front` | `./scripts/test-front.sh` | ESLint + Karma/Jasmine + build de producción |
| `make test-api` | `./scripts/test-api.sh` | Colección de Postman con Newman contra el sistema levantado |
| `make test` | los tres scripts anteriores | Todas las pruebas |
| `make ci` | — | Pipeline completo (igual que GitHub Actions) |
| `make seed` | `docker compose exec api php artisan db:seed --force` | Vuelve a sembrar los datos demo (idempotente) |
| `make mongo` | ver Makefile | Consola de MongoDB con el usuario de la app (mínimo privilegio) |
| `make shell` | `docker compose exec api sh` | Terminal dentro del contenedor de la API |
| `make reset` | `docker compose down -v && docker compose up -d --wait` | ⚠ Borra todos los datos y vuelve a sembrar |
| `make public` *(opcional)* | `./scripts/public.sh` | URL HTTPS temporal con un túnel de Cloudflare (sin cuenta ni tarjeta) |

`make help` lista todo.

---

## 5. Cumplimiento del examen (requisito → dónde está)

| Requisito del PDF | Implementación |
|---|---|
| Backend Laravel 11 + PHP 8.2 | `backend/` · Laravel **11.57.2**, PHP **8.2.36** (imagen `docker/php/Dockerfile`) |
| Frontend Angular 19 + TypeScript | `frontend/` · Angular **19.2.25**, TypeScript **5.8** (Angular 19 exige TS ≥ 5.5, ver nota) |
| BD NoSQL | **MongoDB 8.0**: validación `$jsonSchema`, índices únicos/TTL, `Decimal128`, contador atómico de códigos ([MODELO-DATOS.md](docs/MODELO-DATOS.md)) |
| Git | Repositorio en esta carpeta, rama `feature/prueba-tecnica-tap` |
| Postman o Swagger | **Ambos**: Swagger UI en `/docs/` (`docs/openapi.yaml`) y colección de Postman con ejemplos y pruebas automáticas |
| PSR-12 / convenciones Angular | Laravel **Pint** (preset PSR-12) + **Larastan** nivel 6 · **angular-eslint** |
| Consulta de productos (tabla, PDF, Excel, acciones por permiso) | `features/products/` · `GET /products`, `/products/export?format=pdf\|xlsx` |
| Alta de productos (código automático, precio ≤ 3 dígitos, fecha automática) | `Catalog/Domain/Product.php`, `Price.php` · `POST /products` |
| Consulta/alta de usuarios (foto obligatoria, correo único, teléfono con código de país) | `features/users/` · `IdentityAccess/…/UserService.php` |
| Detalle de usuario (usuario, nombre, teléfono, foto, perfiles) | Modal `user-detail.dialog.ts` · `GET /users/{id}` |
| Perfiles (tabla, PDF/Excel, detalle en modal con secciones) | `features/profiles/` · `GET /profiles/{id}` |
| Login / Logout | `POST /auth/login`, `POST /auth/logout` (+ 2FA y refresh) |
| Recuperación: validar existencia + enviar credenciales | `POST /auth/forgot-password` → 404 si no existe; contraseña temporal al correo registrado |
| Accesos solo a secciones del perfil | Backend: middleware `permission:{sección},{acción}` · Frontend: guards + `*appCan` |
| Cifrado de contraseñas y validaciones | **Argon2id** · FormRequests + Value Objects del dominio + validación en Angular |
| Bitácora (información anterior vs actual) | Colección `audit_logs` con `before`/`after`/`changes` · pantalla **Bitácora** con comparación |
| Extras: CI/CD y pruebas | GitHub Actions: CI (`ci.yml`, 5 jobs) + CD (`cd.yml`, imágenes en ghcr.io) · **64 PHPUnit** + **54 Jasmine** + **87 aserciones Postman** ([CI-CD.md](docs/CI-CD.md)) |
| URL del sistema funcionando | http://localhost:8080 (servidor local, permitido por el examen); opcional `make public` para una URL HTTPS temporal |

> **Nota de versiones.** El examen dice "TypeScript 5.0": Angular 19 no compila con TS 5.0 (requiere
> ≥ 5.5 y < 5.9), por eso se usa 5.8, la más nueva compatible. Laravel 11 y Angular 19 ya están fuera de
> soporte oficial; el examen los exige, así que se mitigaron los avisos de seguridad abiertos
> (ver [docs/SEGURIDAD.md](docs/SEGURIDAD.md)).

---

## 6. Documentación

| Documento | Para qué |
|---|---|
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Arquitectura hexagonal, capas, flujo de una petición, caché, decisiones |
| [docs/GUIA-DEL-CODIGO.md](docs/GUIA-DEL-CODIGO.md) | **Mapa del código**: dónde está cada cosa y "si te piden X, se cambia aquí" |
| [docs/MODELO-DATOS.md](docs/MODELO-DATOS.md) | Modelo NoSQL: documentos reales por colección, validadores, índices y consultas |
| [docs/SEGURIDAD.md](docs/SEGURIDAD.md) | Contraseñas, JWT + 2FA, inyección NoSQL, OWASP, mitigaciones de Laravel 11 |
| [docs/API.md](docs/API.md) | Endpoints, permisos, códigos de error y ejemplos `curl` |
| [docs/CI-CD.md](docs/CI-CD.md) | Pipeline de GitHub Actions, su equivalente local y qué cubre cada prueba |
| [docs/DEMO.md](docs/DEMO.md) | Guion de la demostración en vivo (10-15 min) y preguntas probables |
| [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md) | Docker en detalle, túnel de Cloudflare y solución de problemas |
| [docs/HOSTING.md](docs/HOSTING.md) | *(Opcional, no se usa)* cómo publicarlo en un servidor propio |

---

## 7. Estructura

```
prueba-de-desarrollo/
├── backend/            Laravel 11 (src/ = arquitectura hexagonal por contexto; tests/ = PHPUnit)
├── frontend/           Angular 19 (core / shared / layout / features; *.spec.ts = Jasmine)
├── docker/             Dockerfiles de api (PHP-FPM) y web (Nginx + Angular + Swagger), init de Mongo
├── docs/               Guías, openapi.yaml y postman/ (colección, entorno, foto de ejemplo, generador)
├── scripts/            setup, credenciales, pruebas (test-back/front/api) y túnel público
├── deploy/             (Opcional, no se usa) kit para un servidor propio
├── .github/workflows/  CI (ci.yml) y CD (cd.yml)
├── docker-compose.yml  web · api · worker · mongo · redis · mailpit · tunnel (opcional)
├── Makefile            atajos (make help)
└── .env.example        plantilla de configuración (el .env real nunca se sube a Git)
```

---

## 8. Solución de problemas

| Síntoma | Qué hacer |
|---|---|
| `port is already allocated` (8080) | Cambia `WEB_PORT` en `.env` y corre `make up` |
| Un contenedor no queda *healthy* | `make logs` para ver el error; `make restart` |
| En macOS, `docker pull` o `docker build` se quedan congelados sin avanzar | Docker Desktop está esperando permiso del Llavero (aviso de `docker-credential-desktop`): acepta el aviso o reinicia Docker Desktop |
| `make test-back` dice que el sistema no está levantado | Corre `make up` primero (las pruebas de API usan el MongoDB del stack, en la BD aislada `tap_test`) |
| `make test-front` se queda sin memoria | Sube la memoria de Docker Desktop a 4 GB o más |
| Quiero datos limpios | `make reset` (⚠ borra todo y vuelve a sembrar los usuarios demo) |
