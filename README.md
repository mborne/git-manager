# git-manager

[![CI](https://github.com/mborne/git-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/mborne/git-manager/actions/workflows/ci.yml)

CLI helpers to manage a set of git repositories :

* Retrieve and backup hosted GIT repositories (github, gitlab, gogs, gitea)
* Performs some basic checks (ex : README.md is available)

## Requirements

* PHP >= 7.4

## Parameters

| Name              | Description                           | Default            |
| ----------------- | ------------------------------------- | ------------------ |
| `GIT_MANAGER_DIR` | Directory containing git repositories | `/var/git-manager` |

## Setup

```bash
git clone https://github.com/mborne/git-manager
cd git-manager
composer install
```

## Usage

### Fetch repositories

* From github :

```bash
bin/console git:fetch-all --orgs IGNF --users=mborne https://github.com $GITHUB_TOKEN
# for private repositories, use "_me_" :
bin/console git:fetch-all --users=_me_ https://github.com $GITHUB_TOKEN
```

* From gogs or gitea :

```bash
bin/console git:fetch-all --type gogs-v1 https://codes.quadtreeworld.net $GITEA_RO_TOKEN
```

### Compute stats about repositories

```bash
bin/console git:stats -O stats.json
```

## License

[MIT](LICENSE)

