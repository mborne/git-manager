# git-manager

CLI helpers to manage a set of git repositories :

* Retreive and backup hosted GIT repositories (gitlab, gogs, github)
* Performs some basic checks (ex : README.md is available)

## Usage

### Setup

```bash
git clone https://github.com/mborne/git-manager
cd git-manager
# PHP 7.x
composer install
```

### Fetch repositories

* From github :

```bash
bin/console git:fetch-all --orgs IGNF --users=mborne https://github.com $SATIS_GITHUB_TOKEN
```

* From gogs :

```bash
bin/console git:fetch-all https://gogs.quadtreeworld.net $SATIS_GOGS_TOKEN
```

* ...


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
