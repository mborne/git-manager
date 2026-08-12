#------------------------------------------------------
# Download PHP vendor in dedicated layer
#------------------------------------------------------
FROM composer:2.10 AS builder

WORKDIR /opt/git-manager
COPY composer.json symfony.lock .env .
RUN composer install --no-scripts --prefer-dist
COPY bin bin/
COPY config config/
COPY docs/openapi.yaml docs/openapi.yaml
COPY public public/
COPY src src/
COPY templates templates/

#------------------------------------------------------
# Build the final image using ubuntu, apache2 and mod_php.
#
# PHP and its extensions are installed from the packages provided by
# Ondřej Surý (aka sury) for linux/amd64 and linux/arm64 to avoid
# building the extensions from sources.
#
# see https://launchpad.net/~ondrej/+archive/ubuntu/php
#------------------------------------------------------
FROM ubuntu:24.04

ARG PHP_VERSION=8.4

ENV DEBIAN_FRONTEND=noninteractive

#------------------------------------------------------------------------
# Configure https://packages.sury.org/php/ (ppa:ondrej/php for ubuntu)
#------------------------------------------------------------------------
RUN apt-get update \
 && apt-get install --no-install-recommends -y ca-certificates software-properties-common \
 && add-apt-repository -y ppa:ondrej/php \
 && apt-get purge -y --auto-remove software-properties-common \
 && rm -rf /var/lib/apt/lists/*

#------------------------------------------------------
# Install apache2, PHP and git
#------------------------------------------------------
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      apache2 \
      libapache2-mod-php${PHP_VERSION} \
      php${PHP_VERSION}-cli \
      php${PHP_VERSION}-curl \
      php${PHP_VERSION}-intl \
      php${PHP_VERSION}-mbstring \
      php${PHP_VERSION}-opcache \
      php${PHP_VERSION}-pdo \
      php${PHP_VERSION}-pgsql \
      php${PHP_VERSION}-sqlite3 \
      php${PHP_VERSION}-xml \
      php${PHP_VERSION}-zip \
      git \
 && rm -rf /var/lib/apt/lists/*

# mod_php requires the prefork MPM
RUN a2dismod mpm_event \
 && a2enmod mpm_prefork headers php${PHP_VERSION}

# customize PHP configuration (both apache2 and cli SAPI)
COPY .docker/php.ini /etc/php/${PHP_VERSION}/mods-available/app.ini
RUN phpenmod app

#------------------------------------------------------
# Configure apache2 :
#
# - to serve /app/public on APACHE_PORT (8000 by default so that it can
#   run as www-data without privilege)
# - to send access and error logs to stdout / stderr
#------------------------------------------------------
COPY .docker/apache.conf /etc/apache2/sites-available/git-manager.conf
RUN a2dissite 000-default \
 && a2ensite git-manager \
 && echo 'Listen ${APACHE_PORT}' > /etc/apache2/ports.conf \
 && ln -sfT /dev/stderr /var/log/apache2/error.log \
 && ln -sfT /dev/stdout /var/log/apache2/access.log \
 && ln -sfT /dev/stdout /var/log/apache2/other_vhosts_access.log \
 && chown -R www-data:www-data /var/log/apache2 \
 && mkdir -p /var/run/apache2 /var/lock/apache2 \
 && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2

#------------------------------------------------------
# Install trivy (the official image provides linux/amd64 and linux/arm64
# binaries, arm64 being required for raspberry pi)
#
# see https://github.com/aquasecurity/trivy/pkgs/container/trivy
#------------------------------------------------------
COPY --from=ghcr.io/aquasecurity/trivy:0.73.0 /usr/local/bin/trivy /usr/bin/trivy

ENV TRIVY_CACHE_DIR=/var/trivy/cache
RUN mkdir -p $TRIVY_CACHE_DIR \
 && chown -R www-data:www-data $TRIVY_CACHE_DIR

#------------------------------------------------------
# Install gitleaks (the official image provides linux/amd64 and linux/arm64
# binaries, arm64 being required for raspberry pi)
#
# see https://github.com/gitleaks/gitleaks/pkgs/container/gitleaks
#------------------------------------------------------
COPY --from=ghcr.io/gitleaks/gitleaks:v8.30.1 /usr/bin/gitleaks /usr/bin/gitleaks

#------------------------------------------------------
# Install git-manager
#------------------------------------------------------
WORKDIR /app
COPY --from=builder /opt/git-manager .

# prepare symfony storage directory
RUN mkdir -p /app/var \
 && chown -R www-data:www-data /app/var
VOLUME /app/var

# prepare git-manager storage directory
ENV GIT_MANAGER_DIR=/var/git-manager
RUN mkdir -p /var/git-manager \
&& chown -R www-data:www-data /var/git-manager
VOLUME /var/git-manager

# configure database
ENV DATABASE_URL=sqlite:////var/git-manager/database.db

# customize entrypoint
COPY .docker/run.sh /run.sh
RUN chmod +x /run.sh

# uid=33(www-data) gid=33(www-data) groups=33(www-data)
USER www-data
ENV APACHE_PORT=8000
EXPOSE 8000

ENTRYPOINT ["/run.sh"]
