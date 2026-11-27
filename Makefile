# Atajos del proyecto. Todo corre en Docker: no necesitas PHP, Composer ni Node.
# Cada atajo llama a un script de scripts/ (sirven igual sin `make`, p. ej. en Windows).
.PHONY: help setup up down restart logs ps public public-status unpublish credentials test test-back test-front test-api ci seed reset shell mongo

help: ## Lista los comandos
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  \033[33m%-12s\033[0m %s\n", $$1, $$2}'

setup: ## Primera vez: genera .env, construye imágenes y levanta todo
	@./scripts/setup.sh

up: ## Levanta el stack
	docker compose up -d --wait

down: ## Detiene el stack (conserva los datos)
	docker compose --profile public down

restart: ## Reconstruye imágenes y reinicia
	docker compose build && docker compose up -d --wait

logs: ## Logs en vivo de api, worker y web
	docker compose logs -f api worker web

ps: ## Estado de los contenedores
	docker compose ps

public: ## Crea un túnel HTTPS NUEVO (Cloudflare), lo verifica y muestra la URL
	@./scripts/public.sh

public-status: ## ¿Sigue viva la URL pública?
	@./scripts/public-status.sh

unpublish: ## Cierra el túnel público
	@./scripts/unpublish.sh

credentials: ## Muestra URLs y usuarios demo
	@./scripts/credentials.sh

test: test-back test-front test-api ## Corre todas las pruebas (backend, frontend y API con Postman)

test-api: ## Colección de Postman contra el sistema levantado (Newman en Docker)  (= ./scripts/test-api.sh)
	@./scripts/test-api.sh

ci: ## Pipeline completo como en GitHub Actions: imágenes + lint + análisis + pruebas + API
	docker compose build
	docker compose up -d --wait
	$(MAKE) test-back test-front test-api
	@echo "✔ CI local completo"

test-back: ## PSR-12 (Pint) + análisis estático (Larastan) + PHPUnit  (= ./scripts/test-back.sh)
	@./scripts/test-back.sh

test-front: ## ESLint + pruebas unitarias (Chromium headless) + build  (= ./scripts/test-front.sh)
	@./scripts/test-front.sh

seed: ## Vuelve a correr el seeder (idempotente)
	docker compose exec api php artisan db:seed --force

reset: ## ⚠ Borra TODOS los datos (volúmenes) y vuelve a sembrar
	docker compose --profile public down -v && docker compose up -d --wait

shell: ## Shell dentro del contenedor api
	docker compose exec api sh

mongo: ## Consola de MongoDB con el usuario de la app (mínimo privilegio)
	@docker compose exec mongo sh -c 'mongosh --quiet "mongodb://tap_app:$$MONGO_APP_PASSWORD@localhost:27017/tap?authSource=tap"'
