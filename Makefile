.PHONY: all
all: dist

.PHONY: dist
dist: vendor
	rm -f dist/git-manager.phar
	php -d phar.readonly=0 build-phar.php
	chmod +x dist/git-manager.phar

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
