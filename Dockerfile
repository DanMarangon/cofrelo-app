# syntax=docker/dockerfile:1.7

# ---------------------------------------------------------------------------------------------
# Base comum: PHP 8.3 com Apache e as extensões usadas pelo Cofrelo.
# ---------------------------------------------------------------------------------------------
FROM php:8.3-apache AS base

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
    docker-php-ext-install pdo_mysql opcache; \
    pecl install apcu-5.1.24; \
    docker-php-ext-enable apcu; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/* /tmp/pear; \
    a2enmod rewrite headers; \
    a2disconf other-vhosts-access-log; \
    sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf

COPY docker/apache/cofrelo.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/cofrelo.ini /usr/local/etc/php/conf.d/zz-cofrelo.ini

WORKDIR /var/www/cofrelo

# ---------------------------------------------------------------------------------------------
# Desenvolvimento e testes: adiciona o Composer para instalar o PHPUnit.
# Usado por `docker compose run --rm testes` e nunca publicado.
# ---------------------------------------------------------------------------------------------
FROM base AS dev

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 APP_ENV=development

# ---------------------------------------------------------------------------------------------
# Produção: só o código necessário, rodando como usuário sem privilégios na porta 8080.
# É esta imagem que o pipeline publica no GitHub Container Registry.
# ---------------------------------------------------------------------------------------------
FROM base AS producao

ARG APP_VERSION=dev
ARG APP_COMMIT=local
ENV APP_ENV=production \
    APP_VERSION=${APP_VERSION} \
    APP_COMMIT=${APP_COMMIT}

LABEL org.opencontainers.image.title="Cofrelo" \
      org.opencontainers.image.description="Sistema web de controle financeiro pessoal (PHP e MySQL)" \
      org.opencontainers.image.source="https://github.com/DanMarangon/cofrelo-app" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.revision="${APP_COMMIT}"

COPY --chown=www-data:www-data public/ public/
COPY --chown=www-data:www-data src/ src/
COPY --chown=www-data:www-data views/ views/
COPY --chown=www-data:www-data database/ database/
COPY --chown=www-data:www-data bin/ bin/
RUN mkdir -p storage/logs && chown -R www-data:www-data storage

USER www-data
EXPOSE 8080

HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/health > /dev/null || exit 1
