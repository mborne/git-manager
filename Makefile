.PHONY: all
all: test

.PHONY: test
test: check-style phpstan phpunit

phpunit:
	mkdir -p var/test-data
	rm -rf var/test-data/*
	bin/console --env=test doctrine:schema:update --force
	mkdir -p var/output
	rm -rf var/output/*
	XDEBUG_MODE=coverage \
	SYMFONY_DEPRECATIONS_HELPER='logFile=var/output/deprecations.log' \
		vendor/bin/phpunit \
		--log-junit var/output/junit-report.xml \
		--coverage-clover var/output/clover.xml \
		--coverage-html var/output/coverage

phpstan:
	vendor/bin/phpstan analyse -c phpstan.dist.neon --error-format=raw

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
