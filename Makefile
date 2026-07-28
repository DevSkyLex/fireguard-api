PHP ?= php
PHP_MEMORY_LIMIT ?= 1G
PHPUNIT_BIN ?= vendor/bin/phpunit
PARATEST_BIN ?= vendor/bin/paratest
PARALLEL_WORKERS ?= 8
PHPSTAN_BIN ?= vendor/bin/phpstan
PHPSTAN_CONFIG ?= $(if $(wildcard phpstan.neon),phpstan.neon,phpstan.dist.neon)
PHpat_BIN ?= vendor/bin/phpat
DEPTRAC_BIN ?= vendor/bin/deptrac
CONSOLE_BIN ?= bin/console
PHP_CS_FIXER_BIN ?= vendor/bin/php-cs-fixer
PROJECT_NAME ?= $(notdir $(CURDIR))
APP_ENV ?= dev
# Resolved with make's own functions rather than a $(shell php -r ...) one-liner:
# the quoting of that one-liner breaks under sh, so TMP_DIR came out empty and
# APP_CACHE_DIR resolved to "/fireguard-sso-api/cache/dev" — which under Git
# Bash means Git's own install directory. It went unnoticed only because
# ENV_PREFIX never actually exported the value.
TMP_DIR ?= $(if $(TEMP),$(subst \,/,$(TEMP)),$(if $(TMPDIR),$(TMPDIR),/tmp))
APP_CACHE_DIR ?= $(TMP_DIR)/$(PROJECT_NAME)/cache/$(APP_ENV)
APP_LOG_DIR ?= $(TMP_DIR)/$(PROJECT_NAME)/log/$(APP_ENV)

# Exported rather than prefixed onto each command.
#
# This used to branch on $(OS) and prefix recipes with cmd's `set "VAR=..." &&`
# on Windows. That syntax only works if make actually runs cmd — under Git
# Bash's sh.exe, which is what `make` picks up here, `set` assigns positional
# parameters and silently sets nothing. Console commands then ran with the
# default cache directory, and anything routed this way went to the wrong place
# without a word. `export` is shell-agnostic and cannot fail that way.
export APP_CACHE_DIR
export APP_LOG_DIR

.PHONY: phpunit phpunit-fast phpunit-parallel phpat phpstan deptrac lint cache-clear migrate-auth migrate-main migrate-all test-db test-db-clean seed-fixtures test cs-fix cs-lint coverage coverage-html mutation docker-up docker-down docker-build docker-shell docker-logs

# Run the whole suite: unit, architecture, integration, functional and E2E.


phpunit:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --testdox

phpunit-fast:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN)

# Run the suite across parallel workers.
#
# Each worker clones the databases `make test-db` migrated into its own
# `*_w<token>` copy, so workers never share rows. That clone costs a couple of
# seconds per worker up front, which only pays off over the whole suite — for a
# single testsuite or a --filter run, plain `make phpunit-fast` is faster.
#
# Override the worker count with `make phpunit-parallel PARALLEL_WORKERS=16`.
phpunit-parallel:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PARATEST_BIN) -p $(PARALLEL_WORKERS) --no-coverage

phpstan:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPSTAN_BIN) analyse -c $(PHPSTAN_CONFIG)

deptrac:
	$(PHP) $(DEPTRAC_BIN) analyse --config-file=deptrac.yaml

# Validate Symfony container configuration.
# `lint:container` needs the raised memory limit like phpstan/phpunit do: it
# builds and inspects the whole compiled container, which outgrew PHP's default
# 128M once the modulith passed ~25 modules (it died with OutOfMemoryError).
lint:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) lint:container
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) lint:yaml config --parse-tags

cs-lint:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHP_CS_FIXER_BIN) fix --dry-run --diff

cs-fix:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHP_CS_FIXER_BIN) fix

# Clear and warmup cache to detect configuration errors
cache-clear:
	$(PHP) $(CONSOLE_BIN) cache:clear


# Apply auth database migrations
migrate-auth:
	$(PHP) $(CONSOLE_BIN) doctrine:migrations:migrate --configuration=config/migrations/auth.yaml --no-interaction

# Apply main database migrations
migrate-main:
	$(PHP) $(CONSOLE_BIN) doctrine:migrations:migrate --configuration=config/migrations/main.yaml --no-interaction

# Apply all database migrations
migrate-all: migrate-auth migrate-main

# Create, migrate and seed the PostgreSQL test databases.
#
# The suite runs on PostgreSQL because production does, so the schema comes
# from the real migrations rather than SchemaTool. Run once after `docker-up`,
# then again whenever a new migration or seed fixture lands.
#
# The fixture baseline is seeded here, once, rather than reloaded inside every
# E2E test (~5s each) only for DAMA to roll it back. Tests that need different
# data create it themselves; tests that need a table empty purge it themselves —
# the rollback undoes either. No test may assume it owns the database.
test-db:
	docker exec fireguard-sso-api-auth_database-1 psql -U admin -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='fireguard_auth_test'" | grep -q 1 || docker exec fireguard-sso-api-auth_database-1 psql -U admin -d postgres -c "CREATE DATABASE fireguard_auth_test;"
	docker exec fireguard-sso-api-main_database-1 psql -U main_admin -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='fireguard_main_test'" | grep -q 1 || docker exec fireguard-sso-api-main_database-1 psql -U main_admin -d postgres -c "CREATE DATABASE fireguard_main_test;"
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) doctrine:migrations:migrate --env=test --configuration=config/migrations/auth.yaml --no-interaction
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) doctrine:migrations:migrate --env=test --configuration=config/migrations/main.yaml --no-interaction
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) app:fixtures:load --env=test --no-interaction

# Drop the per-worker database clones left by `make phpunit-parallel`.
#
# Clones are rebuilt on every parallel run, so they never accumulate beyond the
# largest worker count used — but a one-off `-p 32` leaves 64 databases behind.
# The LIKE pattern matches only the `_w<token>` clones, never the migrated
# templates or the dev databases.
test-db-clean:
	docker exec fireguard-sso-api-auth_database-1 psql -U admin -d postgres -tAc "SELECT 'DROP DATABASE IF EXISTS \"' || datname || '\" WITH (FORCE);' FROM pg_database WHERE datname ~ '^fireguard_auth_test_w'" | docker exec -i fireguard-sso-api-auth_database-1 psql -U admin -d postgres
	docker exec fireguard-sso-api-main_database-1 psql -U main_admin -d postgres -tAc "SELECT 'DROP DATABASE IF EXISTS \"' || datname || '\" WITH (FORCE);' FROM pg_database WHERE datname ~ '^fireguard_main_test_w'" | docker exec -i fireguard-sso-api-main_database-1 psql -U main_admin -d postgres

# Load repository seed fixtures into auth and main databases safely
seed-fixtures:
	$(PHP) $(CONSOLE_BIN) app:fixtures:load --no-interaction

test: cs-lint phpstan deptrac lint phpunit-parallel

# Run tests with code coverage (requires PCOV or Xdebug)
#
# XDEBUG_MODE is exported per target, not globally: coverage mode slows every
# PHP process down, and these are the only two that need it.
coverage: export XDEBUG_MODE = coverage
coverage:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --coverage-text --coverage-clover=var/coverage/clover.xml

# Run tests with HTML coverage report
coverage-html: export XDEBUG_MODE = coverage
coverage-html:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --coverage-html=var/coverage/html --coverage-clover=var/coverage/clover.xml
	@echo "Coverage report generated at var/coverage/html/index.html"

# Run mutation testing with Infection (configuration in infection.json5)
# --only-covering-test-cases: run only the test cases that cover the mutated line, not the whole
# test file, dramatically cutting per-mutant execution time on large suites.
mutation:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) vendor/bin/infection --show-mutations --only-covering-test-cases

# Docker commands
docker-build:
	docker compose build

docker-up:
	docker compose up -d
	@echo "Services started. App available at http://localhost:8000"
	@echo "Auth database available at localhost:5433"
	@echo "Main database available at localhost:5434"
	@echo "Redis available at localhost:6379"

docker-down:
	docker compose down

docker-shell:
	docker compose exec app sh

docker-logs:
	docker compose logs -f app

# Start local SonarQube server
sonar-up:
	docker compose -f compose.sonar.yaml up -d sonarqube

# Run SonarQube analysis (requires server to be up)
sonar-scan:
	docker compose -f compose.sonar.yaml run --rm -e SONAR_TOKEN sonar-scanner
