PHP ?= php
PHP_MEMORY_LIMIT ?= 512M
PHPUNIT_BIN ?= vendor/bin/phpunit
PHPSTAN_BIN ?= vendor/bin/phpstan
PHPSTAN_CONFIG ?= $(if $(wildcard phpstan.neon),phpstan.neon,phpstan.dist.neon)
PHpat_BIN ?= vendor/bin/phpat
DEPTRAC_BIN ?= vendor/bin/deptrac
CONSOLE_BIN ?= bin/console
PHP_CS_FIXER_BIN ?= vendor/bin/php-cs-fixer
PROJECT_NAME ?= $(notdir $(CURDIR))
APP_ENV ?= dev
TMP_DIR ?= $(shell $(PHP) -r "echo str_replace('\\\\', '/', sys_get_temp_dir());")
APP_CACHE_DIR ?= $(TMP_DIR)/$(PROJECT_NAME)/cache/$(APP_ENV)
APP_LOG_DIR ?= $(TMP_DIR)/$(PROJECT_NAME)/log/$(APP_ENV)

ifeq ($(OS),Windows_NT)
ENV_PREFIX = set "APP_CACHE_DIR=$(APP_CACHE_DIR)" && set "APP_LOG_DIR=$(APP_LOG_DIR)" &&
XDEBUG_PREFIX = set "XDEBUG_MODE=coverage" &&
else
ENV_PREFIX = APP_CACHE_DIR="$(APP_CACHE_DIR)" APP_LOG_DIR="$(APP_LOG_DIR)"
XDEBUG_PREFIX = XDEBUG_MODE=coverage
endif

.PHONY: phpunit phpunit-fast phpat phpstan deptrac lint cache-clear test cs-fix cs-lint coverage coverage-html mutation docker-up docker-down docker-build docker-shell docker-logs

phpunit:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --testdox

phpunit-fast:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN)

phpstan:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPSTAN_BIN) analyse -c $(PHPSTAN_CONFIG)

deptrac:
	$(PHP) $(DEPTRAC_BIN) analyse --config-file=deptrac.yaml

# Validate Symfony container configuration
lint:
	$(ENV_PREFIX) $(PHP) $(CONSOLE_BIN) lint:container
	$(ENV_PREFIX) $(PHP) $(CONSOLE_BIN) lint:yaml config

cs-lint:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHP_CS_FIXER_BIN) fix --dry-run --diff

cs-fix:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHP_CS_FIXER_BIN) fix

# Clear and warmup cache to detect configuration errors
cache-clear:
	$(ENV_PREFIX) $(PHP) $(CONSOLE_BIN) cache:clear

# Run every test suite and static analysis in sequence
test: cs-lint phpstan deptrac lint phpunit-fast

# Run tests with code coverage (requires PCOV or Xdebug)
coverage:
	$(XDEBUG_PREFIX) $(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --coverage-text --coverage-clover=var/coverage/clover.xml

# Run tests with HTML coverage report
coverage-html:
	$(XDEBUG_PREFIX) $(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPUNIT_BIN) --coverage-html=var/coverage/html --coverage-clover=var/coverage/clover.xml
	@echo "Coverage report generated at var/coverage/html/index.html"

# Run mutation testing with Infection (configuration in infection.json5)
mutation:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) vendor/bin/infection --show-mutations

# Docker commands
docker-build:
	docker compose build

docker-up:
	docker compose up -d
	@echo "Services started. App available at http://localhost:8000"
	@echo "Database available at localhost:5433"
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
