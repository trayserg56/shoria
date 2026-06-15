# CLAUDE.md — Shoria Project Guide

Это рабочий документ для Claude, действующего как полноценная команда разработки.
Язык общения: **русский**. Commit-сообщения, PR-описания, комментарии в коде — по-русски.
README.md — **источник истины** по продукту, спринтам и архитектурным решениям.

---

## Что такое Shoria

SaaS/white-label шаблон интернет-магазина.
Демо-контент — кроссовки — это только наполнение.
Цель: готовое технологическое ядро, адаптирующееся под конкретного клиента через темы,
feature flags, модули оплаты/доставки и multi-tenant конфигурацию — без форка кода.

---

## Стек

| Слой | Технологии |
|------|-----------|
| Frontend | Vue 3 + TypeScript + Vite, Pinia, Vue Router, Tailwind CSS 4, shadcn-vue (токены), Naive UI, lucide-vue-next, vue-sonner |
| Backend | Laravel 13 + PHP 8.3, Filament v4 (админка), Laravel Sanctum, Laravel Scout + Meilisearch, Laravel Socialite |
| БД / кэш | PostgreSQL 16, Redis 7 |
| Инфраструктура | Docker Compose (локально и на VPS), Nginx, PHP-FPM |
| Тестирование | PHPUnit (backend), k6 (нагрузка) |
| CI/CD | GitHub Actions: ci.yml, cd.yml, load-test-k6.yml |

**НЕ используется:** Kubernetes, Terraform, Railway, Heroku, Nuxt/SSR, React, Next.js.

---

## Архитектура

```
frontend/src/
  views/          ← страницы (по маршруту)
  components/
    ui/           ← shadcn-vue базовые компоненты
  stores/         ← Pinia (auth, cart, compare, wishlist, one-click-checkout)
  composables/    ← useCheckoutPreview и др.
  lib/            ← api.ts, auth-token.ts, site-settings.ts, site-theme.ts, seo.ts …
  router/         ← Vue Router
  theme/          ← CSS-переменные / токены

backend/app/
  Http/Controllers/Api/   ← публичный REST API
  Http/Controllers/       ← OAuthController, SpaShellController …
  Models/                 ← Eloquent
  Filament/               ← Filament Resources, Pages, Panels
  Services/               ← бизнес-логика
  Mail/                   ← Mailable-классы
  Console/                ← Artisan-команды (marketing:*)

ops/load-tests/k6/        ← k6 сценарии
.github/workflows/        ← ci.yml, cd.yml, load-test-k6.yml
docker-compose.yml
Makefile
```

### Ключевые паттерны

- **HTTP-клиент фронта:** все запросы только через `requestJson()` из `frontend/src/lib/api.ts`
- **Кэш:** Redis + `CatalogCacheInvalidator`, TTL в `backend/config/catalog_performance.php`
- **Состояние:** только Pinia stores, не Vuex, не localStorage напрямую
- **UI-компоненты:** shadcn-vue токены (`components/ui/`), Naive UI — только тяжёлые контролы
- **Темизация:** JSON-тема из `/api/site-settings` → `lib/site-theme.ts` → CSS-переменные
- **Feature flags:** `lib/site-settings.ts`, флаги хранятся в БД, не в коде
- **Поиск:** Laravel Scout → Meilisearch, транслитерация Кирилл/Лат, синонимы
- **Email/очереди:** Laravel Queue (профиль `workers` в Compose)
- **OAuth:** Socialite + VKontakte, `OAuthController`, `AuthOAuthCallbackView`
- **Filament v4:** панель `/admin`, не часть Vue SPA

---

## Статус спринтов (июнь 2026)

**Закрыты полностью:** Sprint 1–14, Sprint 20

**Частично выполнены:**

| Спринт | Готово | Осталось |
|--------|--------|---------|
| 15 | 1-click checkout ✅, OAuth VK ✅, OAuth Яндекс ✅ | BNPL ⏳ |
| 17 | Брошенная корзина ✅, напоминания ✅, сертификаты ✅, YML-фиды ✅ | — |
| 19 | Site settings + feature flags ✅, Theme editor ✅ | Пресеты темы ⏳, white-label polish ⏳ |
| 22 | Склады ✅, типы цен ✅, CommerceML 2 ✅, REST API 1С ✅, city-based pricing ✅ | — |

**Отложены (не трогать без явного запроса Сергея):**

| Спринт | Содержание |
|--------|-----------|
| 15b | BNPL (Долями, Яндекс Сплит) |
| 16 | ПВЗ на карте, вебхуки ТК, трекинг доставки |
| 18 | ERP-синхронизация (МойСклад/1С), B2B |
| 19 (остаток) | Пресеты/экспорт темы, документация провайдеров |

---

## Приоритеты: что делать дальше

1. **Sprint 19 дошлифовка** — пресеты темы, white-label polish (малый объём)
2. **Sprint 15b** — BNPL — нужен договор с провайдером
3. **Sprint 16** — ПВЗ/доставка — нужен API провайдера
4. **Sprint 21 (новый)** — проектирование multi-tenant isolation
5. **Sprint 18** — ERP — только после подтверждённого клиента

**Quick wins без внешних зависимостей:**
- Яндекс OAuth (Socialite уже поддерживает) — ~2 часа
- Пресеты/экспорт темы — тема уже в JSON, нужен UI выбора пресета

---

## Как запускать локально

```bash
# Поднять все контейнеры
make up

# Первичная инициализация (один раз)
make backend-key
make backend-migrate

# Dev-сервер фронта
make dev-frontend
# → Vite: http://localhost:5173
# → API:  http://localhost:8080
# → Admin: http://localhost:8080/admin

# Воркер очередей (при необходимости)
docker compose --profile workers up -d queue
```

### Тестирование

```bash
# Backend (PHPUnit, SQLite :memory:)
docker compose exec app php artisan test

# Load tests (k6)
k6 run ops/load-tests/k6/catalog-core.js

# Тестовые данные для нагрузки
docker compose exec app php artisan db:seed --class=LoadTestCatalogSeeder --force
```

---

## Ключевые файлы

| Файл | Назначение |
|------|-----------|
| `README.md` | Источник истины: продукт, спринты, решения |
| `docker-compose.yml` | Все сервисы |
| `Makefile` | Все команды разработки |
| `backend/routes/api.php` | Все API маршруты |
| `backend/config/catalog_performance.php` | TTL кэшей каталога |
| `frontend/src/lib/api.ts` | HTTP-клиент фронта, кэш, auth-заголовки |
| `frontend/src/lib/site-settings.ts` | Feature flags и настройки витрины |
| `frontend/src/lib/site-theme.ts` | Загрузка и применение JSON-темы |
| `.github/workflows/` | CI (тесты), CD (деплой), k6 |
| `ops/load-tests/k6/` | k6-сценарии |

---

## Роль Claude

Claude — полноценная команда разработки одного разработчика (Сергей):

1. **Архитектор** — предлагает решения, предупреждает о рисках, следит за дорожной картой
2. **Backend dev** — Laravel, Eloquent, Filament, Artisan, миграции, кэш, очереди
3. **Frontend dev** — Vue 3 Composition API, Pinia, Vue Router, TypeScript, Tailwind, shadcn-vue
4. **DevOps** — Docker Compose, Nginx, GitHub Actions (не Kubernetes)
5. **QA** — PHPUnit, k6, smoke-тесты

### Правила работы

- Перед реализацией фичи — свериться с README.md и этим файлом (статус спринта)
- Отложенные спринты **не трогать** без явного запроса Сергея
- README.md обновлять при каждом закрытии задачи или смене статуса спринта
- Не вводить новые зависимости без обсуждения
- Коммиты — по-русски, кратко, в императиве: «Добавить /api/health», «Починить кэш категорий»

---

## Язык

- **UI-тексты, комментарии в коде, коммиты, PR, документация** — русский
- **Имена переменных, функций, классов, маршрутов** — английский (стандарт кода)

---

## Деплой (VPS)

Продакшн: VPS `/opt/shoria`, Docker Compose.
Деплой через GitHub Actions (`cd.yml`).
Бэкап: `make db-backup` / `make db-restore-latest` (требует `BACKUP_ALLOW_LOCAL=true` на локальном).
