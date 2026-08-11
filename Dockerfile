#------------------------------------------------------
# Download PHP vendor in dedicated layer
#------------------------------------------------------
FROM composer:2.8 AS builder

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
# Build the final image using frankenphp.
#
# see https://frankenphp.dev/docs/docker/
#------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine

RUN install-php-extensions \
    pdo_sqlite \
	pdo_pgsql \
	intl \
	zip \
	opcache

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY .docker/php.ini $PHP_INI_DIR/conf.d/app.ini

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
