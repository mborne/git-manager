.PHONY: all
all: dist

.PHONY: dist
dist: vendor
	rm -f dist/git-manager.phar
	php -d phar.readonly=0 build-phar.php
	chmod +x dist/git-manager.phar

test: vendor
	vendor/bin/phpunit -c phpunit.xml

vendor:
	composer install
