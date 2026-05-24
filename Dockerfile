# syntax=docker/dockerfile:1

# =============================================================================
# Base FrankenPHP image with common extensions
# =============================================================================
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.4-bookworm
FROM ${FRANKENPHP_IMAGE} AS base

# Install system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        openssl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    bcmath \
    redis

# Configure PHP and OPcache
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "expose_php=Off" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "display_errors=Off" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "log_errors=On" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "error_log=/dev/stderr" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/prod.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Traefik terminates TLS; FrankenPHP serves HTTP internally on a non-privileged port.
ENV SERVER_NAME=:8000
ENV SERVER_ROOT=public/
ENV APP_RUNTIME=Symfony\\Component\\Runtime\\SymfonyRuntime

# Create app user and writable runtime directories.
RUN groupadd -g 1000 app \
    && useradd -u 1000 -g app -s /bin/sh -m app \
    && mkdir -p var/cache var/log /data/caddy /config/caddy \
    && chown -R app:app /var/www/html /data /config \
    && (setcap -r /usr/local/bin/frankenphp || true)

# =============================================================================
# Development image
# =============================================================================
FROM base AS dev

# Install PCOV for code coverage and Xdebug for debugging.
RUN install-php-extensions pcov xdebug

# Enable OPcache timestamp validation for development.
RUN echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache-dev.ini

# Configure Xdebug.
RUN echo "xdebug.mode=off" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini

# Set permissions.
RUN mkdir -p var/cache var/log var/coverage var/infection \
    && chown -R app:app /var/www/html

USER app

EXPOSE 8000

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

# =============================================================================
# Production dependencies
# =============================================================================
FROM base AS vendor

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-progress \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# =============================================================================
# Production image
# =============================================================================
FROM base AS prod

# Copy application code.
COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /var/www/html/vendor vendor

# Create required directories.
RUN mkdir -p var/cache var/log \
    && chown -R app:app var /data /config \
    && (setcap -r /usr/local/bin/frankenphp || true)

USER app

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8000/api/health || exit 1

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
