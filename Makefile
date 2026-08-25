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

.PHONY: phpunit phpunit-fast phpunit-parallel phpat phpstan deptrac lint openapi-check cache-clear migrate-auth migrate-main migrate-all test-db test-db-clean test-cache-clean seed-fixtures test cs-fix cs-lint coverage coverage-html mutation docker-up docker-down docker-build docker-shell docker-logs

# Run the whole suite: unit, architecture, integration, functional and E2E.


phpunit:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --testdox

phpunit-fast:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN)

# Run the suite across parallel workers.
#
# Every run — parallel or not — clones the databases `make test-db` migrated
# into a private `*_w<token>` copy and drops it again on exit (see
# tests/bootstrap.php), so runs never share rows with each other or with
# anything else on the machine. Here that also means workers never share rows
# with each other.
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

# Fail when the committed openapi.json no longer matches the code. The spec is
# the contract-of-record the frontend and /fg-contract-check read; it silently
# went eight endpoints stale once, so freshness is part of the gate now.
#
# Compared with `cmp`, not `git diff --no-index`: the latter also compares FILE
# MODES, and the checked-in openapi.json reads 100755 through the Docker
# bind-mount on Windows while the freshly generated one is 100644. The gate then
# failed on the executable bit alone, contents byte-identical — a permanent
# false red that teaches people to ignore it.
openapi-check:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) api:openapi:export --output=$(TMP_DIR)/openapi.fresh.json
	@cmp -s openapi.json $(TMP_DIR)/openapi.fresh.json \
		|| (echo "openapi.json is stale - run: php -d memory_limit=1G bin/console api:openapi:export --output=openapi.json" && exit 1)

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
#
# These two databases are templates: no test run writes to them. Each run
# clones them (tests/bootstrap.php) and works on the copy, so this target is
# the only thing that changes the baseline. It purges before reloading, so
# re-running it always restores exactly the baseline the E2E counts assert.
test-db:
	docker exec fireguard-sso-api-auth_database-1 psql -U admin -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='fireguard_auth_test'" | grep -q 1 || docker exec fireguard-sso-api-auth_database-1 psql -U admin -d postgres -c "CREATE DATABASE fireguard_auth_test;"
	docker exec fireguard-sso-api-main_database-1 psql -U main_admin -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='fireguard_main_test'" | grep -q 1 || docker exec fireguard-sso-api-main_database-1 psql -U main_admin -d postgres -c "CREATE DATABASE fireguard_main_test;"
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) doctrine:migrations:migrate --env=test --configuration=config/migrations/auth.yaml --no-interaction
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) doctrine:migrations:migrate --env=test --configuration=config/migrations/main.yaml --no-interaction
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) app:fixtures:load --env=test --no-interaction

# Drop the per-run database clones left behind by killed test runs.
#
# A run drops its own clones on exit, so they do not normally accumulate. A run
# killed outright (Ctrl-C, SIGKILL) never reaches that shutdown hook and leaks
# two databases; this is the sweep for those. The pattern matches only the
# `_w<token>` clones, never the migrated templates or the dev databases.
test-db-clean:
	docker exec fireguard-sso-api-auth_database-1 psql -U admin -d postgres -tAc "SELECT 'DROP DATABASE IF EXISTS \"' || datname || '\" WITH (FORCE);' FROM pg_database WHERE datname ~ '^fireguard_auth_test_w'" | docker exec -i fireguard-sso-api-auth_database-1 psql -U admin -d postgres
	docker exec fireguard-sso-api-main_database-1 psql -U main_admin -d postgres -tAc "SELECT 'DROP DATABASE IF EXISTS \"' || datname || '\" WITH (FORCE);' FROM pg_database WHERE datname ~ '^fireguard_main_test_w'" | docker exec -i fireguard-sso-api-main_database-1 psql -U main_admin -d postgres

# Drop the compiled container caches the suite keys on its own sources.
#
# tests/bootstrap.php names each cache directory after a fingerprint of config/,
# src/, templates/, translations/, composer.lock and the dotenv files, and
# collects the ones nothing has booted for a week. This is the manual sweep for
# the rest: the directories the old token-keyed layout leaked once per `phpunit`
# run, and anything left over after a `composer update`. Nothing here is state —
# the next run recompiles what it needs, at the usual ~14s.
test-cache-clean:
	rm -rf $(TMP_DIR)/fireguard-auth $(TMP_DIR)/fireguard-auth-*

# Load repository seed fixtures into auth and main databases safely
seed-fixtures:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(CONSOLE_BIN) app:fixtures:load --no-interaction

test: cs-lint phpstan deptrac lint openapi-check phpunit-parallel

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
