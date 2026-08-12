#!/bin/sh
set -e

cd /app
php bin/console cache:clear
php bin/console doctrine:schema:update --force

# load APACHE_LOG_DIR, APACHE_PID_FILE, APACHE_RUN_DIR, ... required by apache2.conf
. /etc/apache2/envvars

# apache2 refuses to start if a pid file remains from a previous run
rm -f "$APACHE_PID_FILE"

exec apache2 -DFOREGROUND
