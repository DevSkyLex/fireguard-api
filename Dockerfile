# syntax=docker/dockerfile:1

# =============================================================================
# Base PHP image with common extensions
# =============================================================================
FROM php:8.5-cli-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    icu-dev \
    libzip-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    bcmath

# Install PCOV for code coverage (faster than Xdebug)
RUN pecl install pcov && docker-php-ext-enable pcov

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Configure OPcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# =============================================================================
# Development image
# =============================================================================
FROM base AS dev

# Enable OPcache timestamp validation for development
RUN echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache-dev.ini

# Install Xdebug for debugging (optional, PCOV is used for coverage)
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configure Xdebug
RUN echo "xdebug.mode=off" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini

# Create app user
RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

# Set permissions
RUN mkdir -p var/cache var/log var/coverage var/infection \
    && chown -R app:app /var/www/html

USER app

# Expose PHP built-in server port
EXPOSE 8000

# Default command: start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

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
    --no-progress

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative

# =============================================================================
# Production image
# =============================================================================
FROM base AS prod

# Create app user
RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

# Copy application code
COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /var/www/html/vendor vendor

# Set production PHP settings
RUN echo "expose_php=Off" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "display_errors=Off" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "log_errors=On" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "error_log=/dev/stderr" >> /usr/local/etc/php/conf.d/prod.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/prod.ini

# Create required directories
RUN mkdir -p var/cache var/log \
    && chown -R app:app var

USER app

# Warm up cache
RUN php bin/console cache:warmup --env=prod

EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8000/api/health || exit 1

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
