# git-manager

**WARNING : WORK IN PROGRESS**

## Description

CLI helpers to manage hosted git repositories. 

## Usage

### Setup

```bash
git clone https://github.com/mborne/git-manager
cd git-manager
# PHP 7.x
composer install
# PHP 5.6 (downgrading versions refered in composer.lock is required)
composer update
```

### Create local data directory

Default local data dir is "$PWD/data". You may change with using `--data` option.

```bash
mkdir data
```

### Fetch repositories

```bash
bin/git-manager git:fetch-all https://github.com --users=mborne $SATIS_GITHUB_TOKEN
```

## License

mborne/git-manager is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
