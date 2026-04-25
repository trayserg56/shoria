SHELL := /bin/bash
COMPOSE ?= docker compose
BACKEND ?= backend
APP_SMOKE_URL ?= http://localhost:8080
BACKUP_ALLOW_LOCAL ?= false
PROD_APP_PATH ?= /opt/shoria

.PHONY: help copy-env build up down init-backend init-frontend filament-install db-backup db-restore-latest media-backup media-restore-latest backup-full restore-full-latest ops-security-smoke

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
	@echo "  make media-backup    — архив медиафайлов (storage/app/public) в ./backups/media"
	@echo "  make media-restore-latest — восстановить медиа из последнего архива"
	@echo "  make backup-full     — единый бэкап БД + медиа с общим timestamp"
	@echo "  make restore-full-latest — восстановить БД + медиа из последних full-бэкапов"
	@echo "  make ops-security-smoke — smoke-проверка security headers и API"

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
	if [ "$$(pwd)" != "$(PROD_APP_PATH)" ] && [ "$(BACKUP_ALLOW_LOCAL)" != "true" ]; then \
		echo "Локальные backup отключены. Запускайте на production: $(PROD_APP_PATH)"; \
		echo "Для локальной диагностики явно укажите BACKUP_ALLOW_LOCAL=true"; \
		exit 1; \
	fi; \
	mkdir -p backups/db; \
	stamp=$$(date +%Y%m%d_%H%M%S); \
	file="backups/db/shoria_$$stamp.sql"; \
	echo "Создаю бэкап: $$file"; \
	$(COMPOSE) exec -T postgres pg_dump -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" > "$$file"; \
	echo "Готово: $$file"

db-restore-latest:
	@set -e; \
	if [ "$$(pwd)" != "$(PROD_APP_PATH)" ] && [ "$(BACKUP_ALLOW_LOCAL)" != "true" ]; then \
		echo "Локальные restore отключены. Запускайте на production: $(PROD_APP_PATH)"; \
		echo "Для локальной диагностики явно укажите BACKUP_ALLOW_LOCAL=true"; \
		exit 1; \
	fi; \
	latest=$$(ls -1t backups/db/shoria_*.sql 2>/dev/null | head -n 1); \
	if [ -z "$$latest" ]; then echo "Бэкапы не найдены в backups/db"; exit 1; fi; \
	echo "Восстанавливаю из: $$latest"; \
	$(COMPOSE) exec -T postgres psql -v ON_ERROR_STOP=1 -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"; \
	$(COMPOSE) exec -T postgres psql -v ON_ERROR_STOP=1 -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" < "$$latest"; \
	echo "Восстановление завершено"

media-backup:
	@set -e; \
	if [ "$$(pwd)" != "$(PROD_APP_PATH)" ] && [ "$(BACKUP_ALLOW_LOCAL)" != "true" ]; then \
		echo "Локальные backup отключены. Запускайте на production: $(PROD_APP_PATH)"; \
		echo "Для локальной диагностики явно укажите BACKUP_ALLOW_LOCAL=true"; \
		exit 1; \
	fi; \
	mkdir -p backups/media backend/storage/app/public; \
	stamp=$$(date +%Y%m%d_%H%M%S); \
	file="backups/media/shoria_media_$$stamp.tar.gz"; \
	echo "Создаю бэкап медиа: $$file"; \
	tar -czf "$$file" -C backend/storage/app/public .; \
	echo "Готово: $$file"

media-restore-latest:
	@set -e; \
	if [ "$$(pwd)" != "$(PROD_APP_PATH)" ] && [ "$(BACKUP_ALLOW_LOCAL)" != "true" ]; then \
		echo "Локальные restore отключены. Запускайте на production: $(PROD_APP_PATH)"; \
		echo "Для локальной диагностики явно укажите BACKUP_ALLOW_LOCAL=true"; \
		exit 1; \
	fi; \
	latest=$$(ls -1t backups/media/shoria_media_*.tar.gz 2>/dev/null | head -n 1); \
	if [ -z "$$latest" ]; then echo "Архивы медиа не найдены в backups/media"; exit 1; fi; \
	echo "Восстанавливаю медиа из: $$latest"; \
	mkdir -p backend/storage/app/public; \
	find backend/storage/app/public -mindepth 1 -maxdepth 1 -exec rm -rf {} +; \
	tar -xzf "$$latest" -C backend/storage/app/public; \
	echo "Восстановление медиа завершено"

backup-full:
	@set -e; \
	if [ "$$(pwd)" != "$(PROD_APP_PATH)" ] && [ "$(BACKUP_ALLOW_LOCAL)" != "true" ]; then \
		echo "Локальные backup отключены. Запускайте на production: $(PROD_APP_PATH)"; \
		echo "Для локальной диагностики явно укажите BACKUP_ALLOW_LOCAL=true"; \
		exit 1; \
	fi; \
	mkdir -p backups/db backups/media backend/storage/app/public; \
	stamp=$$(date +%Y%m%d_%H%M%S); \
	db_file="backups/db/shoria_$$stamp.sql"; \
	media_file="backups/media/shoria_media_$$stamp.tar.gz"; \
	echo "Создаю full backup [$$stamp]"; \
	echo " - БД: $$db_file"; \
	$(COMPOSE) exec -T postgres pg_dump -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" > "$$db_file"; \
	echo " - Медиа: $$media_file"; \
	tar -czf "$$media_file" -C backend/storage/app/public .; \
	echo "Готово: $$db_file + $$media_file"

restore-full-latest:
	@set -e; \
	if [ "$$(pwd)" != "$(PROD_APP_PATH)" ] && [ "$(BACKUP_ALLOW_LOCAL)" != "true" ]; then \
		echo "Локальные restore отключены. Запускайте на production: $(PROD_APP_PATH)"; \
		echo "Для локальной диагностики явно укажите BACKUP_ALLOW_LOCAL=true"; \
		exit 1; \
	fi; \
	latest_db=$$(ls -1t backups/db/shoria_*.sql 2>/dev/null | head -n 1); \
	latest_media=$$(ls -1t backups/media/shoria_media_*.tar.gz 2>/dev/null | head -n 1); \
	if [ -z "$$latest_db" ]; then echo "SQL-бэкап не найден в backups/db"; exit 1; fi; \
	if [ -z "$$latest_media" ]; then echo "Архив медиа не найден в backups/media"; exit 1; fi; \
	echo "Восстанавливаю full backup:"; \
	echo " - БД: $$latest_db"; \
	echo " - Медиа: $$latest_media"; \
	$(COMPOSE) exec -T postgres psql -v ON_ERROR_STOP=1 -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"; \
	$(COMPOSE) exec -T postgres psql -v ON_ERROR_STOP=1 -U "$${POSTGRES_USER:-shoria}" -d "$${POSTGRES_DB:-shoria}" < "$$latest_db"; \
	mkdir -p backend/storage/app/public; \
	find backend/storage/app/public -mindepth 1 -maxdepth 1 -exec rm -rf {} +; \
	tar -xzf "$$latest_media" -C backend/storage/app/public; \
	echo "Full restore завершен"

ops-security-smoke:
	@set -e; \
	echo "Проверяю security headers на $(APP_SMOKE_URL)"; \
	headers=$$(curl -sSI "$(APP_SMOKE_URL)"); \
	echo "$$headers" | grep -qi "X-Frame-Options:"; \
	echo "$$headers" | grep -qi "X-Content-Type-Options:"; \
	echo "$$headers" | grep -qi "Referrer-Policy:"; \
	echo "$$headers" | grep -qi "Permissions-Policy:"; \
	echo "$$headers" | grep -qi "Content-Security-Policy:"; \
	echo "Проверяю API доступность"; \
	curl -sS "$(APP_SMOKE_URL)/api/products" | grep -Eq "\\[|\\{"; \
	echo "Smoke-проверка пройдена"
