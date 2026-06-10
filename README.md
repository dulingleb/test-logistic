# Notification Service

Сервис массовой отправки уведомлений с идемпотентным приёмом, очередями по приоритету, retry с DLQ и обработкой провайдерских callback'ов.

**Стек:** Laravel 13 (PHP 8.5) · PostgreSQL 17 · Redis 7 · RabbitMQ 4 · Nginx · Docker Compose.

---

## Как собрать и запустить

### Требования
- Docker 20+
- Docker Compose v2
- `make` (или вызывать команды из `Makefile` руками)

Других зависимостей на хосте не нужно — PHP, composer, БД и брокер живут в контейнерах.

### Первый запуск (одна команда)

```bash
make init
```

Что делает:
1. Создаёт `.env` из `.env.example`, если его ещё нет.
2. Собирает образ `notification-app:local` (Dockerfile в `docker/php/`).
3. Поднимает контейнеры: `app` (php-fpm), `nginx`, `worker`, `postgres`, `redis`, `rabbitmq`.
4. Ждёт готовности `app`.
5. `composer install` внутри контейнера.
6. `php artisan key:generate` (если `APP_KEY` ещё пустой).
7. `php artisan migrate --force` — накатывает миграции на postgres.

После этого:
- API: <http://localhost:8080>
- RabbitMQ Management UI: <http://localhost:15672> (логин/пароль `guest` / `guest`)
- Postgres: `localhost:5432` (пользователь `notifications`, пароль `secret`, БД `notifications`)
- Redis: `localhost:6379`

Проверить, что всё ОК:

```bash
curl http://localhost:8080/up                 # 200 = liveness
curl http://localhost:8080/api/health/ready   # 200 + перечень зависимостей
```

### Повседневные команды

```bash
make up               # старт всех сервисов
make down             # остановить контейнеры
make restart          # рестарт
make ps               # статус контейнеров
make logs             # tail логов; svc=worker — фильтр по сервису
make shell            # bash внутри app-контейнера
make artisan args="route:list"   # любая artisan-команда
make composer args="require ..." # любая composer-команда
make migrate          # php artisan migrate
make fresh            # migrate:fresh --seed (сносит данные)
make cache-clear      # optimize:clear
make test             # быстрый тест-сьют
make test-integration # интеграционный сьют (нужны поднятые сервисы)
```

### Если что-то не поднялось

```bash
make logs svc=app        # PHP-FPM
make logs svc=worker     # очередной воркер
make logs svc=postgres   # БД
make logs svc=rabbitmq   # брокер
docker compose ps        # статусы и порты
```

Worker подтягивает изменённый код только после перезапуска:

```bash
docker compose restart worker
```

### Полный сброс

```bash
make down
docker volume rm test-logistic_postgres-data test-logistic_redis-data test-logistic_rabbitmq-data
make init
```

---

## Архитектура

```
┌──────────┐  POST /api/notifications/bulk    ┌─────────────────┐
│  Client  │ ───────────────────────────────► │ BulkNotification│
└──────────┘                                  │   Controller    │
     ▲   ▲                                    └────────┬────────┘
     │   │                                             │
     │   │ 200/202 + Idempotent-Replayed              ▼
     │   │                                    ┌─────────────────┐
     │   │                                    │ BulkDispatcher  │
     │   │                                    │ (lock + persist)│
     │   │                                    └────────┬────────┘
     │   │                                             │
     │   │            ┌────────────────────────────────┼───────────────────────┐
     │   │            ▼                                ▼                       ▼
     │   │     ┌──────────────┐                ┌──────────────┐         ┌──────────────┐
     │   │     │ PostgreSQL   │                │ RabbitMQ     │         │  Redis       │
     │   │     │ notifications│ ◄─────┐        │ transactional│         │ idempotency  │
     │   │     │ events       │       │        │ marketing    │         │   cache+lock │
     │   │     │ bulks        │       │        │ dlq          │         └──────────────┘
     │   │     │ idempotency  │       │        └──────┬───────┘
     │   │     └──────┬───────┘       │               │
     │   │            │               │               ▼
     │   │            │               │       ┌──────────────┐
     │   │            │               └───────┤ Worker       │
     │   │            │ status updates        │ queue:work   │
     │   │            └───────────────────────┤ (приоритет:  │
     │   │                                    │ transactional│
     │   │                                    │ → marketing) │
     │   │                                    └──────┬───────┘
     │   │                                           │
     │   │                                           ▼
     │   │                                    ┌──────────────────┐
     │   │                                    │ NotificationSender│
     │   │                                    │ (row lock, status │
     │   │                                    │  transitions)    │
     │   │                                    └──────┬───────────┘
     │   │                                           │
     │   │                                           ▼
     │   │                                    ┌──────────────────┐
     │   │                                    │ Provider stubs   │
     │   │                                    │ (email / sms)    │
     │   │                                    └──────┬───────────┘
     │   │                                           │
     │   │  POST /api/notifications/webhooks/{p}    │
     │   └───────────────────────────────────────────┘
     │                                               (async delivered/failed callback)
     │
     └─── GET /api/notifications/by-recipient/{id}, /api/metrics, /api/health/ready
```

**Ключевые сервисы:**
- `BulkDispatcher` — принимает payload, дедуплицирует по `Idempotency-Key`, в одной БД-транзакции пишет bulk + строки на каждого получателя + начальные события `queued`, потом диспатчит джобы в очередь приоритета.
- `NotificationSender` — вызывается воркером, блокирует строку (`FOR UPDATE`), зовёт провайдера канала, двигает статус (`queued → sent`, `→ failed`, retry-события), пишет структурные логи.
- `DeliveryConfirmationService` — применяет внешние callback'и (`sent → delivered` или `sent → failed`), отказывается перезаписывать терминальные статусы.
- `DeadLetterNotificationJob` — marker-джоба, паркуется в `notifications.dlq` после исчерпания retry; в payload'е достаточно контекста для replay.

---

## API

### POST `/api/notifications/bulk`

Приём массовой рассылки. Идемпотентен по `Idempotency-Key`.

```bash
curl -X POST http://localhost:8080/api/notifications/bulk \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: campaign-2026-06-10-batch-1' \
  -d '{
    "channel": "email",
    "priority": "transactional",
    "message": "Привет!",
    "recipients": ["a@example.com", "b@example.com"]
  }'
```

Ответ: `202 Accepted` на первый вызов, `200 OK` на replay; header `Idempotent-Replayed: true|false`.

**Валидация:**
- `channel` — `email` или `sms`
- `priority` — `transactional` или `marketing`
- `message` — строка, 1–10000 символов
- `recipients` — массив из 1–10000 элементов; формат зависит от канала (RFC email или телефон `+E.164`)
- `Idempotency-Key` header (опционально) — 1–64 символа; повторное использование ключа с **другим** payload'ом → `409 idempotency_conflict`

### GET `/api/notifications/by-recipient/{recipient_id}`

История по получателю с полным таймлайном событий.

```bash
curl 'http://localhost:8080/api/notifications/by-recipient/a@example.com?per_page=50'
```

`per_page` ограничен `[1, 200]`. По умолчанию `50`.

### POST `/api/notifications/webhooks/{provider}`

Callback провайдера для перехода `sent → delivered` или `sent → failed`.

```bash
curl -X POST http://localhost:8080/api/notifications/webhooks/postmark \
  -H 'Content-Type: application/json' \
  -H 'X-Webhook-Secret: $NOTIFICATIONS_WEBHOOK_SECRET' \
  -d '{
    "provider_message_id": "email_abc123",
    "status": "delivered",
    "occurred_at": "2026-06-10T11:00:00Z",
    "meta": {"region": "eu-1"}
  }'
```

Исходы: `200 applied`, `200 already_applied`, `409 conflict` (попытка сменить терминальный статус), `404 not_found`, `403` (плохой секрет).

### GET `/api/metrics`

Prometheus text exposition. Счётчики по `(status, channel, priority)` и сумма attempts по status.

```
notifications_total{status="sent",channel="email",priority="transactional"} 421
notifications_attempts_total{status="failed"} 17
```

### GET `/api/health/ready` и `/up`

- `/up` — liveness, отвечает 200 если жив PHP-FPM.
- `/api/health/ready` — readiness; пингует Postgres, Redis, RabbitMQ. Возвращает `200 ready` или `503 degraded` с детальным статусом каждой зависимости.

---

## Тесты

```bash
make test               # быстрый сьют: SQLite + sync queue (38 тестов, ~0.6с)
make test-integration   # реальные PG/Redis/RabbitMQ (7 тестов, ~0.7с)
```

**Быстрый сьют** покрывает идемпотентность, валидацию, state machine sender'а, retry-и-DLQ через прямые вызовы, исходы webhook'а, recipient history, метрики, маршрутизацию очередей.

**Интеграционный сьют** использует `phpunit.integration.xml` и отдельную БД `notifications_test` (создаётся автоматически при первом прогоне). Доказывает:
- **Приоритет очередей** end-to-end через реального воркера: transactional обрабатывается раньше marketing, даже если marketing пришли первыми.
- **PG-специфичные** инварианты схемы — `jsonb` round-trip с UTF-8/emoji, `unique` на nullable `provider_message_id`, FK cascade на двух уровнях, идемпотентность через Redis-кэш с fallback в PG.

Запуск с фильтром:

```bash
docker compose exec app php artisan test --filter test_idempotent
docker compose exec app php artisan test tests/Feature/Api
```

---

## Решения по дизайну

### Идемпотентность
- Header `Idempotency-Key` (≤ 64 символов). Hash = SHA-256 от канонического JSON `(channel, priority, message, recipients)`.
- Сначала проверка кэша (Redis); fallback на таблицу `idempotency_keys`, если кэш вытеснен.
- Тот же ключ + тот же hash → отдаём закэшированный body (`200 OK`, `Idempotent-Replayed: true`).
- Тот же ключ + другой hash → `409 idempotency_conflict`.
- Параллельный запрос с тем же ключом → Redis-lock; второй ждёт либо replay'ит, либо получает таймаут → `409 concurrent_request`.

### Очереди по приоритету
- `priority=transactional` → `notifications.transactional`
- `priority=marketing` → `notifications.marketing`
- Воркер запускается с `--queue=notifications.transactional,notifications.marketing` — Laravel вычерпывает первую очередь до конца перед тем, как трогать вторую. Доказано end-to-end в `PriorityOrderingTest`.

### Гарантии доставки
- **At-least-once** на транспортном уровне: ack джобы только после успешного возврата `NotificationSender::send`. Падение воркера в середине handle'а → сообщение приедет снова.
- **Exactly-once** на бизнес-уровне: `lockForUpdate` + проверка статуса (`if (status !== Queued) return`). Повторная доставка джобы для уже отправленной нотификации — no-op. Вызов провайдера происходит максимум один раз на принятую `Queued` строку.
- State machine: `queued → sent → delivered | failed`, либо `queued → failed`. Терминальные статусы (`delivered`, `failed`) перезаписать нельзя — webhook вернёт `409 conflict`.

### Retry-политика
- `tries = 5`, `backoff = [1, 5, 30, 300]` секунд с jitter ±25%.
- Transient `Throwable` от провайдера → sender персистит `attempts++` + retry-событие, перебрасывает. Laravel перепланирует с backoff.
- `PermanentProviderException` → джоба зовёт `$this->fail()` → сразу finalize как Failed, без оставшихся ретраев.
- После исчерпания попыток `SendNotificationJob::failed()` финализирует Failed и пушит `DeadLetterNotificationJob` в `notifications.dlq` с `notification_id`, причиной, попытками, классом исключения.

### Provider-стабы
- `EmailProviderStub`, `SmsProviderStub` пишут структурную строку и возвращают синтетический `provider_message_id`.
- Knob'ы (env): `NOTIFICATIONS_PERMANENT_FAILURE_RATE`, `NOTIFICATIONS_TRANSIENT_FAILURE_RATE`, `NOTIFICATIONS_PROVIDER_DELAY_MIN_MS`, `NOTIFICATIONS_PROVIDER_DELAY_MAX_MS`.
- Эмуляция async-доставки: при `NOTIFICATIONS_DELIVERY_CALLBACK_ENABLED=true` стабы диспатчат delayed `ConfirmDeliveryJob` (случайная задержка), который зовёт тот же `DeliveryConfirmationService`, что и webhook. Шанс fail-callback'а — `NOTIFICATIONS_DELIVERY_CALLBACK_FAILURE_RATE`.

### Observability
- **Структурные логи** через канал `notifications` (Monolog `JsonFormatter` → `stderr`). По одному событию на переход статуса (`provider.send`, `notification.sent`, `notification.retry`, `notification.failed`, `notification.dlq_replayed`).
- **Метрики**: `GET /api/metrics` (Prometheus text format) — счётчики по `(status, channel, priority)` и агрегированные attempts.
- **Health**: `GET /up` (liveness), `GET /api/health/ready` (readiness с пингами зависимостей).

---

## Конфигурация

Все knob'ы живут в `config/notifications.php` и читаются из env. Дефолты безопасны для прода.

| Env | По умолчанию | Назначение |
|---|---|---|
| `NOTIFICATIONS_SEND_TRIES` | `5` | Общее число попыток send-job |
| `NOTIFICATIONS_DLQ` | `notifications.dlq` | Имя dead-letter очереди |
| `NOTIFICATIONS_PERMANENT_FAILURE_RATE` | `0` | Эмуляция permanent-сбоя `[0..1]` |
| `NOTIFICATIONS_TRANSIENT_FAILURE_RATE` | `0` | Эмуляция transient-сбоя `[0..1]` |
| `NOTIFICATIONS_PROVIDER_DELAY_MIN_MS` / `MAX_MS` | `0` / `0` | Случайный sleep внутри стаба |
| `NOTIFICATIONS_DELIVERY_CALLBACK_ENABLED` | `false` | Эмулировать async `delivered` callback |
| `NOTIFICATIONS_DELIVERY_CALLBACK_DELAY_MIN` / `MAX` | `1` / `10` | Диапазон задержки callback'а в секундах |
| `NOTIFICATIONS_DELIVERY_CALLBACK_FAILURE_RATE` | `0` | Шанс, что callback вернёт `failed`, а не `delivered` |
| `NOTIFICATIONS_WEBHOOK_SECRET` | _(пусто)_ | При установке — обязательный header `X-Webhook-Secret` |

---

## Структура проекта

```
app/
├── DTO/                              # NewBulkDataDTO, BulkDispatchResultDTO
├── Enums/                            # Channel, Priority, Status
├── Exceptions/                       # IdempotencyConflict, PermanentProvider
├── Http/
│   ├── Controllers/Api/              # Bulk, History, Webhook, Health, Metrics
│   └── Requests/                     # StoreBulk, ProviderCallback
├── Jobs/                             # SendNotificationJob, ConfirmDeliveryJob, DeadLetterNotificationJob
├── Models/                           # Notification, NotificationBulk, NotificationEvent, IdempotencyKey
└── Services/
    ├── Idempotency/                  # IdempotencyStoreService
    └── Notifications/
        ├── BulkDispatcher.php
        ├── NotificationSender.php
        ├── DeliveryConfirmationService.php
        ├── RecipientHistoryQuery.php
        └── Providers/                # NotificationProvider, EmailProviderStub, SmsProviderStub, ProviderRegistry, SimulatesFailures/Latency/AsyncDelivery
tests/
├── Unit/                             # DTO hash, enum routing, retry policy
├── Feature/                          # API, pipeline, retry/DLQ, metrics
└── Integration/                      # Реальные PG/Redis/RabbitMQ
```
