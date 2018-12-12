# git-manager

## Description

CLI helpers to manage hosted git repositories.

## Use cases

* Backup remove and self-hosted repositories (gitlab, gogs, github)
* Performs some basic checks (ex : README.md is available)

## Usage

### Setup

```bash
git clone https://github.com/mborne/git-manager
cd git-manager
# PHP 7.x
composer install
# PHP 5.6 (downgrading versions refered in composer.lock is required)
composer update
```

### Fetch repositories

```bash
bin/console git:fetch-all https://github.com --users=mborne $SATIS_GITHUB_TOKEN
```

### Compute stats about repositories

```bash
bin/console git:stats -O stats.json
```

### View stats

```bash
bin/console server:run
```

## License

mborne/git-manager is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
