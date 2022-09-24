#!/bin/bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
PROJECT_DIR=$(dirname $SCRIPT_DIR)

php ${PROJECT_DIR}/dist/git-manager.phar git:fetch-all --users=mborne https://gitlab.com $GITLAB_TOKEN
