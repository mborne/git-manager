.PHONY: all
all: test

.PHONY: test
test: check-style check-rules
	bash .ci/run-test.sh

.PHONY: check-rules
check-rules: phpstan

phpstan:
	vendor/bin/phpstan analyse -c phpstan.neon --error-format=raw

.PHONY: fix-style
fix-style: vendor
	@echo "-- Fixing coding style using php-cs-fixer..."
	vendor/bin/php-cs-fixer fix src
	vendor/bin/php-cs-fixer fix tests

.PHONY: check-style
check-style: vendor
	@echo "-- Checking coding style using php-cs-fixer (run 'make fix-style' if it fails)"
	vendor/bin/php-cs-fixer fix src -v --dry-run --diff
	vendor/bin/php-cs-fixer fix tests -v --dry-run --diff

.PHONY: vendor
vendor:
	composer install

.PHONY: clean
clean:
	rm -rf vendor dist/git-manager.phar
