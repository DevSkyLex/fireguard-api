PHP ?= php
PHP_MEMORY_LIMIT ?= 512M
PHPUNIT_BIN ?= vendor/bin/phpunit
PHPSTAN_BIN ?= vendor/bin/phpstan
PHpat_BIN ?= vendor/bin/phpat

.PHONY: phpunit phpat phpstan test

phpunit:
	$(PHP) $(PHPUNIT_BIN)

phpstan:
	$(PHP) -d memory_limit=$(PHP_MEMORY_LIMIT) $(PHPSTAN_BIN) analyse -c phpstan.dist.neon

# Run every test suite and static analysis in sequence
 test: phpstan phpunit
