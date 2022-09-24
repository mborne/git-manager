#!/bin/bash

if [ -z "$GITLAB_TOKEN"];
then
    echo "GITLAB_TOKEN required"
    exit 1
fi

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
PROJECT_DIR=$(dirname $SCRIPT_DIR)

git config credential.helper '!f() { sleep 1; echo "username=user"; echo "password=${GITLAB_TOKEN}"; }; f'

php ${PROJECT_DIR}/dist/git-manager.phar git:fetch-all --users=mborne https://gitlab.com $GITLAB_TOKEN

if [ ! -e "$DATA_DIR/gitlab.com/mborne/sample-composer/README.md" ];
then
    echo "gitlab.com/mborne/sample-composer/README.md not found!"
    exit 1
fi
