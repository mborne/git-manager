#------------------------------------------------------
# Download PHP vendor in dedicated layer
#------------------------------------------------------
FROM composer:2.8 AS builder

WORKDIR /opt/git-manager
COPY composer.json symfony.lock .env .
RUN composer install --no-scripts --prefer-dist
COPY bin bin/
COPY config config/
COPY public public/
COPY src src/
COPY templates templates/

#------------------------------------------------------
# Download /usr/bin/trivy in dedicated layer
#------------------------------------------------------
FROM ubuntu:24.04 AS trivy

RUN apt-get update \
 && apt-get install -y wget apt-transport-https gnupg lsb-release \
 # Install trivy
 && wget -qO - https://aquasecurity.github.io/trivy-repo/deb/public.key | apt-key add - \
 && echo "deb https://aquasecurity.github.io/trivy-repo/deb $(lsb_release -sc) main" | tee -a /etc/apt/sources.list.d/trivy.list \
 && apt-get update \
 && apt-get install -y trivy \
 && rm -rf /var/cache/apt/*

#------------------------------------------------------
# Build the final image using frankenphp.
#
# see https://frankenphp.dev/docs/docker/
#------------------------------------------------------
FROM dunglas/frankenphp:1-php8.3-alpine

RUN install-php-extensions \
    pdo_sqlite \
	pdo_pgsql \
	intl \
	zip \
	opcache

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY .docker/php.ini $PHP_INI_DIR/conf.d/app.ini

#------------------------------------------------------
# Install trivy
#------------------------------------------------------
COPY --from=trivy /usr/bin/trivy /usr/bin/trivy

ENV TRIVY_CACHE_DIR=/var/trivy/cache
RUN mkdir -p $TRIVY_CACHE_DIR \
 && chown -R www-data:www-data $TRIVY_CACHE_DIR

#------------------------------------------------------
# Install git
#------------------------------------------------------
RUN apk add --no-cache git

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

# fix permissions for caddy
RUN chown -R www-data:www-data /data/caddy \
 && chown -R www-data:www-data /config/caddy	

# customize entrypoint
COPY .docker/run.sh /run.sh
RUN chmod +x /run.sh

# uid=82(www-data) gid=82(www-data) groups=82(www-data)
USER www-data
ENV SERVER_NAME=:8000
EXPOSE 8000

ENTRYPOINT ["/run.sh"]
