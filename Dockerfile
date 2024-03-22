# Download PHP vendor in dedicated layer
FROM composer:2.7 as builder

RUN mkdir /opt/git-manager
COPY composer.json /opt/git-manager/.
WORKDIR /opt/git-manager
RUN composer install

# Download /usr/bin/trivy in dedicated layer
FROM ubuntu:22.04 as trivy

RUN apt-get update \
 && apt-get install -y wget apt-transport-https gnupg lsb-release \
 # Install trivy
 && wget -qO - https://aquasecurity.github.io/trivy-repo/deb/public.key | apt-key add - \
 && echo "deb https://aquasecurity.github.io/trivy-repo/deb $(lsb_release -sc) main" | tee -a /etc/apt/sources.list.d/trivy.list \
 && apt-get update \
 && apt-get install -y trivy \
 && rm -rf /var/cache/apt/*

# Build git-manager image
FROM php:8.2-apache

COPY --from=trivy /usr/bin/trivy /usr/bin/trivy

RUN apt-get update \
 && apt-get install -y git wget \
 # Install PHP extensions
 && docker-php-ext-install opcache \
 && rm -rf /var/cache/apt/*

#----------------------------------------------------------------------
# Configure PHP
#----------------------------------------------------------------------
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini

#----------------------------------------------------------------------
# Configure apache
#----------------------------------------------------------------------
COPY .docker/apache-ports.conf /etc/apache2/ports.conf
COPY .docker/apache-security.conf /etc/apache2/conf-enabled/security.conf
COPY .docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

COPY . /opt/git-manager
WORKDIR /opt/git-manager
COPY --from=builder /opt/git-manager/vendor vendor

ENV HOME=/var/git-manager
RUN chown -R www-data:www-data /opt/git-manager/var \
 && mkdir /var/git-manager && chown -R www-data:www-data /var/git-manager
VOLUME /var/git-manager

USER www-data
EXPOSE 8000

