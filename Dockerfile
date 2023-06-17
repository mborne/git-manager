FROM composer:2.3 as builder

RUN mkdir /opt/git-manager
COPY composer.json /opt/git-manager/.
WORKDIR /opt/git-manager
RUN composer install

FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y git \
 && docker-php-ext-install opcache \
 && rm -rf /var/cache/apt/*

COPY . /opt/git-manager
WORKDIR /opt/git-manager
COPY --from=builder /opt/git-manager/vendor vendor

RUN chown -R www-data:www-data /opt/git-manager/var \
 && mkdir /var/git-manager && chown -R www-data:www-data /var/git-manager
VOLUME /var/git-manager

USER www-data
