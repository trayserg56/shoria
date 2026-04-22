SHELL := /bin/bash
COMPOSE ?= docker compose
BACKEND ?= backend

.PHONY: help copy-env build up down init-backend init-frontend filament-install db-backup db-restore-latest

help:
	@echo "Shoria — команды:"
	@echo "  make copy-env        — cp .env.example .env (docker-compose переменные)"
	@echo "  make build           — собрать образ PHP"
	@echo "  make up              — postgres + redis + app + nginx"
	@echo "  make down            — остановить"
	@echo "  make init-backend    — Laravel в ./backend через Composer в Docker"
	@echo "  make init-frontend   — Vue SPA в ./frontend (интерактивно или см. README)"
	@echo "  make filament-install — Filament в уже созданном backend (в контейнере)"
	@echo "  make dev-frontend    — Vite dev server (профиль frontend)"
	@echo "  make db-backup       — SQL-бэкап текущей БД в ./backups/db"
	@echo "  make db-restore-latest — восстановить БД из последнего SQL-бэкапа"

copy-env:
	@test -f .env || cp .env.example .env

build: copy-env
	$(COMPOSE) build app

up: copy-env build
	$(COMPOSE) up -d postgres redis app nginx

down:
	$(COMPOSE) down

init-backend:
	@test -d $(BACKEND) && test -f $(BACKEND)/artisan && echo "backend уже похож на Laravel" && exit 1 || true
	mkdir -p $(BACKEND)
	$(COMPOSE) --profile tools run --rm composer create-project laravel/laravel . --prefer-dist --no-interaction
	@echo "Дальше: настройте backend/.env (DB_* уже совпадают с docker-compose), затем make up && make backend-migrate"

backend-key:
	$(COMPOSE) exec app php artisan key:generate

backend-migrate:
	$(COMPOSE) exec app php artisan migrate

filament-install:
	$(COMPOSE) exec app composer require filament/filament:"^4.0" -W --no-interaction
	$(COMPOSE) exec app php artisan filament:install --panels --no-interaction

init-frontend:
	@test -f frontend/package.json && echo "frontend уже инициализирован" && exit 1 || true
	$(COMPOSE) --profile tools run --rm node-tools sh -c "npm create vue@latest frontend -- --typescript --router --pinia"

dev-frontend:
	$(COMPOSE) --profile frontend up node

db-backup:
	@set -e; \
	mkdir -p backups/db; \
	stamp=$$(date +%Y%m%d_%H%M%S); \
	file="backups/db/shoria_$$stamp.sql"; \
	echo "Создаю бэкап: $$file"; \
	$(COMPOSE) exec -T postgres pg_dump -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" > "$$file"; \
	echo "Готово: $$file"

db-restore-latest:
	@set -e; \
	latest=$$(ls -1t backups/db/shoria_*.sql 2>/dev/null | head -n 1); \
	if [ -z "$$latest" ]; then echo "Бэкапы не найдены в backups/db"; exit 1; fi; \
	echo "Восстанавливаю из: $$latest"; \
	$(COMPOSE) exec -T postgres psql -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" < "$$latest"; \
	echo "Восстановление завершено"
