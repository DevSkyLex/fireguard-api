# Operations Guide

This document describes how to run Fireguard Auth Server in a production or staging environment.

## Runtime Requirements

- PHP 8.4+
- Composer dependencies installed
- Database (PostgreSQL by default; configurable via `DATABASE_URL`)
- Write access to `var/` for cache and logs

## Configuration Checklist

1. Environment variables:
   - `APP_ENV`, `APP_SECRET`
   - `DATABASE_URL`
   - `OAUTH_ISSUER`, `OAUTH_ENCRYPTION_KEY`
   - Token and cookie TTLs
   - `CORS_ALLOW_ORIGIN`, `DEFAULT_URI`
2. JWT keys:
   - `config/jwt/private.key`
   - `config/jwt/public.key`
3. Messenger transport:
   - `MESSENGER_TRANSPORT_DSN`

## Deployment Steps

1. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
2. Configure environment variables for the target environment.
3. Run database migrations:
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
4. Warm up cache:
   ```bash
   php bin/console cache:clear
   ```
5. Start the HTTP runtime (php-fpm, Symfony runtime, or container).

## Background Workers

If Messenger transports are configured for async processing, run workers:
```bash
php bin/console messenger:consume -vv
```

Tune worker counts, timeouts, and retry settings to match traffic and SLA requirements.

## Database Operations

- Apply new migrations on deploy.
- Monitor connection pools and long-running queries.
- Backup and restore should include all OAuth and auth-related tables.

## Logs and Monitoring

- Symfony logs are stored in `var/log` by default.
- Monitor authentication errors, token issuance rates, and rate limiter rejections.
- Use application metrics and logs to detect abuse or anomalous traffic.

## Rollback Strategy

- Keep previous builds and environment configs.
- Roll back application code and re-run migrations only when compatible.
- Maintain backups for DB and configuration secrets.
