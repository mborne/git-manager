#!/bin/bash

if [ -z "$GITLAB_TOKEN" ];
then
    echo "GITLAB_TOKEN required"
    exit 1
fi

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
PROJECT_DIR=$(dirname $SCRIPT_DIR)

# Config auth for "git clone"
git config --global credential.helper store
echo "https://user:${GITLAB_TOKEN}@gitlab.com" > ~/.git-credentials

# Fetch mborne repos from gitlab.com
php ${PROJECT_DIR}/dist/git-manager.phar git:fetch-all --users=mborne https://gitlab.com $GITLAB_TOKEN

# Ensure it works
if [ ! -e "$GIT_MANAGER_DIR/gitlab.com/mborne/sample-composer/README.md" ];
then
    echo "gitlab.com/mborne/sample-composer/README.md not found!"
    exit 1
fi
