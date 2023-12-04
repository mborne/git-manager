.PHONY: all
all: dist

.PHONY: dist
dist: vendor
	rm -f dist/git-manager.phar
	php -d phar.readonly=0 build-phar.php
	chmod +x dist/git-manager.phar

.PHONY: test
test: check-style
	mkdir -p var/output
	rm -rf var/output/*
	XDEBUG_MODE=coverage \
	SYMFONY_DEPRECATIONS_HELPER='logFile=var/output/deprecations.log' \
		vendor/bin/phpunit -c phpunit.xml.dist \
		--log-junit var/output/junit-report.xml \
		--coverage-clover var/output/clover.xml \
		--coverage-html var/output/coverage

.PHONY: fix-style
fix-style: vendor
	@echo "-- Fixing coding style using php-cs-fixer..."
	vendor/bin/php-cs-fixer fix src

.PHONY: check-style
check-style: vendor
	@echo "-- Checking coding style using php-cs-fixer (run 'make fix-style' if it fails)"
	vendor/bin/php-cs-fixer fix src -v --dry-run --diff --using-cache=no

.PHONY: vendor
vendor:
	composer install

.PHONY: clean
clean:
	rm -rf vendor dist/git-manager.phar
