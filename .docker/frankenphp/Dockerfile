# syntax=docker/dockerfile:1.7
# ==============================================================================
# WordPress Boilerplate — FrankenPHP image
#
# Multi-stage:
#   base  = extensions + composer + wp-cli (dipakai dev & prod)
#   dev   = base + xdebug + source mount (fast iteration)
#   prod  = base + composer install --no-dev + code frozen, non-root, hardened
# ==============================================================================

ARG FRANKENPHP_VERSION=1.4
ARG PHP_VERSION=8.3

# ------------------------------------------------------------------------------
# base — extensions & tooling yang sama untuk dev/prod
# ------------------------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/etc/php/conf.d"

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        less \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libmagickwand-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
        default-mysql-client \
    && install-php-extensions \
        gd \
        imagick \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        redis \
        zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY .docker/frankenphp/php.ini       /usr/local/etc/php/php.ini
COPY .docker/frankenphp/Caddyfile     /etc/caddy/Caddyfile
COPY .docker/frankenphp/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# Strip Windows CRLF di semua file text yang di-consume Linux (safety net
# kalau file di-edit di Windows editor tanpa hormatin .gitattributes)
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh /etc/caddy/Caddyfile /usr/local/etc/php/php.ini \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /app

# FrankenPHP writable dirs.
RUN mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /data /config

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

EXPOSE 80 443 443/udp

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://127.0.0.1:80/healthz || exit 1

# ------------------------------------------------------------------------------
# dev — Xdebug + source di-mount live (composer install jalan di entrypoint)
# ------------------------------------------------------------------------------
FROM base AS dev

RUN install-php-extensions xdebug \
    && echo "xdebug.mode=develop,debug"        >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "opcache.validate_timestamps=1"    >> /usr/local/etc/php/conf.d/opcache-dev.ini \
    && echo "opcache.revalidate_freq=0"        >> /usr/local/etc/php/conf.d/opcache-dev.ini

ENV APP_ENV=development

# ------------------------------------------------------------------------------
# vendor — install composer deps di layer terpisah supaya cache-able
# ------------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
COPY plugins-custom/ ./plugins-custom/

RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-scripts \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

# ------------------------------------------------------------------------------
# prod — code frozen, no dev tools, non-root
# ------------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=production

RUN echo "opcache.validate_timestamps=0"       > /usr/local/etc/php/conf.d/opcache-prod.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache-prod.ini \
    && echo "opcache.memory_consumption=256"      >> /usr/local/etc/php/conf.d/opcache-prod.ini \
    && echo "opcache.interned_strings_buffer=32"  >> /usr/local/etc/php/conf.d/opcache-prod.ini \
    && echo "opcache.jit=tracing"                 >> /usr/local/etc/php/conf.d/opcache-prod.ini \
    && echo "opcache.jit_buffer_size=128M"        >> /usr/local/etc/php/conf.d/opcache-prod.ini

# Copy application code (project files) then vendored deps from previous stage.
COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --chown=www-data:www-data config/          ./config/
COPY --chown=www-data:www-data web/             ./web/
COPY --chown=www-data:www-data plugins-custom/  ./plugins-custom/
COPY --chown=www-data:www-data --from=vendor /app/vendor/    ./vendor/
COPY --chown=www-data:www-data --from=vendor /app/web/wp/    ./web/wp/
COPY --chown=www-data:www-data --from=vendor /app/web/app/plugins/    ./web/app/plugins/
COPY --chown=www-data:www-data --from=vendor /app/web/app/mu-plugins/ ./web/app/mu-plugins/

# Uploads harus writable saat runtime — dibuat sebagai volume mount.
RUN mkdir -p /app/web/app/uploads /app/web/app/cache /app/web/app/upgrade \
    && chown -R www-data:www-data /app/web/app/uploads /app/web/app/cache /app/web/app/upgrade

USER www-data
