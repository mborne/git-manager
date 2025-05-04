#!/bin/sh
set -e

cd /app
php bin/console cache:clear
php bin/console doctrine:schema:update --force

exec docker-php-entrypoint --config /etc/caddy/Caddyfile --adapter caddyfile
