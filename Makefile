.PHONY: up down build test lint stan install shell

## Build and start containers
up:
	docker compose up -d --build

## Stop containers
down:
	docker compose down

## Build image only
build:
	docker compose build

## Install composer dependencies
install:
	docker compose run --rm app composer install

## Run PHPUnit tests
test:
	docker compose run --rm app ./vendor/bin/phpunit

## Run PHP_CodeSniffer (PSR-12)
lint:
	docker compose run --rm app ./vendor/bin/php-cs-fixer fix src tests --dry-run --diff --allow-risky=yes

## Fix code style
fix:
	docker compose run --rm app ./vendor/bin/php-cs-fixer fix src tests --allow-risky=yes

## Run PHPStan static analysis
stan:
	docker compose run --rm app ./vendor/bin/phpstan analyse

## Open shell in app container
shell:
	docker compose run --rm app bash

