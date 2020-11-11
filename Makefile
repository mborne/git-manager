.PHONY: all
all: dist

.PHONY: dist
dist: vendor
	php -d phar.readonly=0 build-phar.php

test: vendor
	vendor/bin/phpunit -c phpunit.xml

vendor:
	composer install
