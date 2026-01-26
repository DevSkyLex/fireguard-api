PHP ?= php
PHP_MEMORY_LIMIT ?= 512M
PHPUNIT_BIN ?= vendor/bin/phpunit
PHPSTAN_BIN ?= vendor/bin/phpstan
PHPSTAN_CONFIG ?= $(if $(wildcard phpstan.neon),phpstan.neon,phpstan.dist.neon)
PHpat_BIN ?= vendor/bin/phpat
DEPTRAC_BIN ?= vendor/bin/deptrac
CONSOLE_BIN ?= bin/console
PHP_CS_FIXER_BIN ?= vendor/bin/php-cs-fixer
APP_ENV ?= dev
TMP_DIR ?= $(shell $(PHP) -r "echo str_replace('\\\\', '/', sys_get_temp_dir());")
APP_CACHE_DIR ?= $(TMP_DIR)/fireguard-auth/cache/$(APP_ENV)
APP_LOG_DIR ?= $(TMP_DIR)/fireguard-auth/log/$(APP_ENV)

ifeq ($(OS),Windows_NT)
ENV_PREFIX = set "APP_CACHE_DIR=$(APP_CACHE_DIR)" && set "APP_LOG_DIR=$(APP_LOG_DIR)" &&
else
ENV_PREFIX = APP_CACHE_DIR="$(APP_CACHE_DIR)" APP_LOG_DIR="$(APP_LOG_DIR)"
endif

.PHONY: phpunit phpunit-fast phpat phpstan deptrac lint cache-clear test cs-fix cs-lint

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

# Start local SonarQube server
sonar-up:
	docker compose -f compose.sonar.yaml up -d sonarqube

# Run SonarQube analysis (requires server to be up)
sonar-scan:
	docker compose -f compose.sonar.yaml run --rm -e SONAR_TOKEN sonar-scanner
