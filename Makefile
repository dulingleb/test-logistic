DC := docker compose
APP := $(DC) exec app

export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: help init build up down restart logs ps shell composer artisan migrate fresh test cache-clear

help:
	@echo "Targets:"
	@echo "  init         Build, start, install deps, key:generate, migrate (one-shot)"
	@echo "  build        Build app image"
	@echo "  up           Start all services in background"
	@echo "  down         Stop and remove containers"
	@echo "  restart      Restart all services"
	@echo "  logs         Tail logs (svc=<service> to filter)"
	@echo "  ps           Show running containers"
	@echo "  shell        Open shell in app container"
	@echo "  composer     Run composer (args=\"install\")"
	@echo "  artisan      Run artisan (args=\"route:list\")"
	@echo "  migrate      Run migrations"
	@echo "  fresh        Drop and re-migrate"
	@echo "  test         Run phpunit (fast suite: sqlite + sync queue)"
	@echo "  test-integration  Run integration suite against real pg/redis/rabbitmq"
	@echo "  cache-clear  Clear config/route/cache"

init:
	@test -f .env || cp .env.example .env
	$(DC) build
	$(DC) up -d
	@echo "Waiting for app container to be ready..."
	@until $(DC) exec -T app php -v >/dev/null 2>&1; do sleep 1; done
	$(APP) composer install --no-interaction --prefer-dist
	@grep -q '^APP_KEY=base64:' .env || $(APP) php artisan key:generate
	$(APP) php artisan migrate --force
	@echo ""
	@echo "Ready: http://localhost:$${APP_PORT:-8080}  |  RabbitMQ UI: http://localhost:15672"

build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart

logs:
	$(DC) logs -f $(svc)

ps:
	$(DC) ps

shell:
	$(APP) bash

composer:
	$(APP) composer $(args)

artisan:
	$(APP) php artisan $(args)

migrate:
	$(APP) php artisan migrate

fresh:
	$(APP) php artisan migrate:fresh --seed

test:
	$(APP) php artisan test

test-integration:
	$(APP) php artisan test -c phpunit.integration.xml

cache-clear:
	$(APP) php artisan optimize:clear
