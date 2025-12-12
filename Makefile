PHP ?= php
PHP_MEMORY_LIMIT ?= 512M
PHPUNIT_BIN ?= vendor/bin/phpunit
PHPSTAN_BIN ?= vendor/bin/phpstan
PHpat_BIN ?= vendor/bin/phpat
DEPTRAC_BIN ?= vendor/bin/deptrac
CONSOLE_BIN ?= bin/console

.PHONY: phpunit phpat phpstan deptrac lint cache-clear test

phpunit:
	$(PHP) $(PHPUNIT_BIN) --testdox

phpstan:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPSTAN_BIN) analyse -c phpstan.dist.neon

deptrac:
	$(PHP) $(DEPTRAC_BIN) analyse --config-file=deptrac.yaml

# Validate Symfony container configuration
lint:
	$(PHP) $(CONSOLE_BIN) lint:container
	$(PHP) $(CONSOLE_BIN) lint:yaml config

# Clear and warmup cache to detect configuration errors
cache-clear:
	$(PHP) $(CONSOLE_BIN) cache:clear

# Run every test suite and static analysis in sequence
test: phpstan deptrac lint phpunit

# Start local SonarQube server
sonar-up:
	docker compose -f compose.sonar.yaml up -d sonarqube

# Run SonarQube analysis (requires server to be up)
sonar-scan:
	docker compose -f compose.sonar.yaml run --rm -e SONAR_TOKEN sonar-scanner