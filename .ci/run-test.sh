#!/bin/bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
PROJECT_DIR=$(dirname $SCRIPT_DIR)

cd $PROJECT_DIR

export APP_ENV=test

# reset database
rm -rf var/data/git-manager-test.db
bin/console cache:clear
bin/console doctrine:schema:update --force


# prepare output dir for test
mkdir -p var/output
rm -rf var/output/*

# run test
export XDEBUG_MODE=coverage
export SYMFONY_DEPRECATIONS_HELPER='logFile=var/output/deprecations.log'

export $(grep -v '^#' .env.test | xargs)

echo $GIT_MANAGER_DIR

vendor/bin/phpunit -c phpunit.xml.dist \
  --log-junit var/output/junit-report.xml \
  --coverage-clover var/output/clover.xml \
  --coverage-html var/output/coverage

