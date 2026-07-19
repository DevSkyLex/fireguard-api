# Operations Guide

This document provides operational procedures, runbooks, and monitoring guidance for Fireguard API in production and staging environments.

---

## Table of Contents

- [Deployment](#deployment)
  - [Prerequisites](#prerequisites)
  - [Environment Setup](#environment-setup)
  - [Database Migrations](#database-migrations)
  - [JWT Key Generation](#jwt-key-generation)
  - [Deployment Checklist](#deployment-checklist)
- [Health Checks](#health-checks)
- [Monitoring](#monitoring)
  - [Key Metrics](#key-metrics)
  - [Log Analysis](#log-analysis)
  - [Alerting Thresholds](#alerting-thresholds)
- [Runbooks](#runbooks)
  - [Token Revocation (Emergency)](#token-revocation-emergency)
  - [JWT Key Rotation](#jwt-key-rotation)
  - [Rate Limit Adjustment](#rate-limit-adjustment)
  - [User Lockout Recovery](#user-lockout-recovery)
  - [Database Connection Issues](#database-connection-issues)
  - [High Memory/CPU Usage](#high-memorycpu-usage)
- [Scheduled Tasks](#scheduled-tasks)
  - [Data Cleanup](#data-cleanup)
  - [Backup Strategy](#backup-strategy)
- [Scaling Guidelines](#scaling-guidelines)
- [Troubleshooting](#troubleshooting)

---

## Deployment

### Prerequisites

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 8.4+ | 8.4+ |
| PostgreSQL | 14+ | 16+ |
| Memory | 256 MB | 512 MB+ |
| Disk | 1 GB | 5 GB+ |

### Environment Setup

1. **Copy environment template**:
   ```bash
   cp .env .env.local
   ```

2. **Configure critical variables**:
   ```bash
   # Application
   APP_ENV=prod
   APP_SECRET=<generate-with-openssl-rand-hex-32>

   # Databases
   AUTH_DATABASE_URL="postgresql://auth_user:password@host:5432/fireguard_auth?sslmode=require"
   MAIN_DATABASE_URL="postgresql://main_user:password@host:5432/fireguard_main?sslmode=require"

   # OAuth2
   OAUTH_ISSUER=https://auth.yourdomain.com
   OAUTH_ENCRYPTION_KEY=<base64-encoded-32-byte-key>

   # Security
   CORS_ALLOW_ORIGIN='^https://(app\.yourdomain\.com)$'
   COOKIE_SECURE=true
   ```

3. **Generate encryption key**:
   ```bash
   # Generate OAUTH_ENCRYPTION_KEY
   php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
   ```

### Database Migrations

**Pre-deployment check**:
```bash
# List pending auth migrations
php bin/console doctrine:migrations:status

# List pending main migrations
php bin/console doctrine:migrations:status --configuration=config/migrations/main.yaml

# Dry-run auth migrations (review SQL)
php bin/console doctrine:migrations:migrate --dry-run

# Dry-run main migrations (review SQL)
php bin/console doctrine:migrations:migrate --configuration=config/migrations/main.yaml --dry-run
```

**Execute migrations**:
```bash
# Apply auth migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Apply main migrations
php bin/console doctrine:migrations:migrate --configuration=config/migrations/main.yaml --no-interaction

# Verify auth schema
php bin/console doctrine:schema:validate --em=auth

# Verify main schema
php bin/console doctrine:schema:validate --em=main
```

### Object Storage (STORAGE_DSN)

`Shared\Application\Port\Outbound\FileStoragePort` (equipment attachments,
user avatars, organization logos) is backed by a Flysystem operator selected
by the `STORAGE_DSN` env var (`Shared\Infrastructure\Storage\FlysystemFactory`).
See `src/Shared/MODULE.md` for the DSN grammar and adapter details.

**Local disk (dev/test/default)**:
```env
STORAGE_DSN=local://var/storage
```
Relative paths resolve against `%kernel.project_dir%`; use an absolute path
(`local:///data/storage` or `local://C:\data\storage`) to point elsewhere.

**S3/MinIO (staging/prod)**:
```env
STORAGE_DSN=s3://<accessKeyId>:<secretAccessKey>@<bucket>?region=<region>
# MinIO / non-AWS S3-compatible endpoint (both params usually required together):
STORAGE_DSN=s3://<accessKeyId>:<secretAccessKey>@<bucket>?region=us-east-1&endpoint=<url-encoded-endpoint>&use_path_style_endpoint=1
```
- `endpoint` must be URL-encoded (e.g. `http%3A%2F%2Fminio.internal%3A9000`).
- `use_path_style_endpoint=1` is required by most MinIO deployments (bucket
  addressed as a path segment rather than a subdomain).
- Credentials containing special characters (`:`, `@`, `/`) must also be
  URL-encoded in the DSN.

**MinIO bucket bootstrap** (example with the `mc` CLI):
```bash
mc alias set fireguard http://minio.internal:9000 <accessKeyId> <secretAccessKey>
mc mb fireguard/fireguard-storage
mc anonymous set none fireguard/fireguard-storage
```

**One-time prod file migration** (local disk -> S3/MinIO):

Storage keys are relative and unchanged by the backend switch (equipment
attachments, avatar variants, org logos), so cutover is a plain bulk copy at
identical keys — no `storagePath` column changes, no schema migration:
```bash
# Using the AWS CLI
aws s3 sync var/storage/ s3://fireguard-storage/ --no-progress

# Using the MinIO client
mc mirror var/storage/ fireguard/fireguard-storage/
```
Run the sync, verify a sample of keys resolve (avatar `GET`, a known
equipment attachment `GET`), then flip `STORAGE_DSN` to `s3://...` and
restart the app. The sync is idempotent and safe to re-run before cutover.

### RBAC Permission Sync

When permissions are updated in code (fixtures/catalog), synchronize them to the database:

```bash
php bin/console app:authz:sync-permissions --update-roles
```

Use `--dry-run` to preview changes without writing to the database.

### Test Coverage (HTML)

HTML coverage is generated on demand:

```bash
vendor/bin/phpunit --coverage-html var/coverage/html
```

**Rollback** (if needed):
```bash
# Rollback last auth migration
php bin/console doctrine:migrations:migrate prev

# Rollback last main migration
php bin/console doctrine:migrations:migrate --configuration=config/migrations/main.yaml prev

# Rollback auth to specific version
php bin/console doctrine:migrations:migrate DoctrineMigrations\\Auth\\Version20240101000000
```

### JWT Key Generation

**Generate RSA key pair**:
```bash
# Create directory
mkdir -p config/jwt

# Generate private key (encrypted)
openssl genrsa -out config/jwt/private.key -aes256 4096

# Extract public key
openssl rsa -in config/jwt/private.key -pubout -out config/jwt/public.key

# Set permissions
chmod 600 config/jwt/private.key
chmod 644 config/jwt/public.key
```

**For automated deployments** (no passphrase):
```bash
openssl genrsa -out config/jwt/private.key 4096
openssl rsa -in config/jwt/private.key -pubout -out config/jwt/public.key
```

### Deployment Checklist

- [ ] Environment variables configured
- [ ] Database migrations applied
- [ ] JWT keys in place with correct permissions
- [ ] Cache cleared and warmed
- [ ] TLS/HTTPS configured on load balancer
- [ ] Health check endpoint responding
- [ ] Rate limiters verified
- [ ] CORS origins verified
- [ ] Logging pipeline connected
- [ ] Monitoring dashboards configured

**Clear and warm cache**:
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

---

## Health Checks

### Application Health

**Basic health check** (HTTP 200 = healthy):
```bash
curl -s -o /dev/null -w "%{http_code}" https://auth.yourdomain.com/api/.well-known/openid-configuration
```

**Expected response**: `200` with JSON containing `issuer`, `token_endpoint`, etc.

### Database Connectivity

```bash
# Auth database
php bin/console doctrine:query:sql "SELECT 1" --connection=auth --env=prod

# Main database
php bin/console doctrine:query:sql "SELECT 1" --connection=main --env=prod
```

### Cache Status

```bash
php bin/console cache:pool:list
php bin/console cache:pool:prune
```

### Container/Service Health

```bash
# Verify Symfony container
php bin/console debug:container --env=prod | head -20

# Check for deprecations
php bin/console debug:container --deprecations --env=prod
```

---

## Monitoring

### Key Metrics

| Metric | Description | Alert Threshold |
|--------|-------------|-----------------|
| `auth.login.success` | Successful logins | Baseline |
| `auth.login.failure` | Failed logins | > 100/min |
| `auth.login.rate_limited` | Rate-limited requests | > 50/min |
| `oauth.token.issued` | Tokens issued | Baseline |
| `oauth.token.revoked` | Tokens revoked | Spike detection |
| `mfa.verification.success` | MFA successes | Baseline |
| `mfa.verification.failure` | MFA failures | > 20/min |
| `http.response_time.p95` | 95th percentile latency | > 500ms |
| `http.error.5xx` | Server errors | > 10/min |

### Log Analysis

**Security log location** (production):
- Stream: `php://stderr`
- Format: JSON
- Channel: `security`

**Search for failed logins**:
```bash
# Using jq for JSON logs
cat /var/log/auth/security.log | jq 'select(.message | contains("login_failed"))'

# Count failures by IP (last hour)
cat /var/log/auth/security.log | jq -r 'select(.context.event == "login_failed") | .context.ip_hash' | sort | uniq -c | sort -rn | head -10
```

**Search for token revocations**:
```bash
cat /var/log/auth/security.log | jq 'select(.context.event == "token_revoked")'
```

### Alerting Thresholds

| Alert | Condition | Severity | Action |
|-------|-----------|----------|--------|
| High Login Failures | > 100 failures/min | Warning | Review IPs, consider blocking |
| Rate Limit Surge | > 200 limited/min | Warning | Check for attacks |
| 5xx Errors | > 10/min | Critical | Check logs, rollback if needed |
| Database Latency | p95 > 100ms | Warning | Review queries, connections |
| Token Revocation Spike | > 50/min | Warning | Possible breach, investigate |
| Memory Usage | > 80% | Warning | Scale or investigate leaks |

---

## Runbooks

### Token Revocation (Emergency)

**Scenario**: Suspected credential compromise or security breach.

**Steps**:

1. **Identify scope**:
   ```bash
   # Count active tokens for user
   php bin/console doctrine:query:sql \
     "SELECT COUNT(*) FROM oauth_access_tokens WHERE user_id = 'USER_ID' AND expiry > NOW()"
   ```

2. **Revoke all tokens for a user**:
   ```sql
   -- Via database (immediate)
   UPDATE oauth_access_tokens SET revoked = true WHERE user_id = 'USER_ID';
   UPDATE oauth_refresh_tokens SET revoked = true WHERE user_id = 'USER_ID';
   ```

3. **Revoke all tokens for a client**:
   ```sql
   UPDATE oauth_access_tokens SET revoked = true WHERE client_id = 'CLIENT_ID';
   UPDATE oauth_refresh_tokens SET revoked = true WHERE client_id = 'CLIENT_ID';
   ```

4. **Force password reset**:
   ```sql
   UPDATE users SET password_change_required = true WHERE id = 'USER_ID';
   ```

5. **Clear token cache**:
   ```bash
   php bin/console cache:pool:clear cache.app
   ```

### JWT Key Rotation

**Scenario**: Scheduled key rotation or compromised key.

**Steps**:

1. **Generate new keys** (keep old public key temporarily):
   ```bash
   # Backup old keys
   cp config/jwt/private.key config/jwt/private.key.old
   cp config/jwt/public.key config/jwt/public.key.old

   # Generate new keys
   openssl genrsa -out config/jwt/private.key 4096
   openssl rsa -in config/jwt/private.key -pubout -out config/jwt/public.key
   ```

2. **Deploy new keys** to all instances

3. **Wait for old token expiry** (ACCESS_TOKEN_TTL, default 1 hour)

4. **Remove old keys**:
   ```bash
   rm config/jwt/private.key.old config/jwt/public.key.old
   ```

5. **Verify JWKS endpoint**:
   ```bash
   curl https://auth.yourdomain.com/api/.well-known/jwks.json | jq
   ```

### Rate Limit Adjustment

**Scenario**: Legitimate traffic spike or attack mitigation.

**Temporary adjustment** (runtime):
```yaml
# config/packages/rate_limiter.yaml
framework:
  rate_limiter:
    login:
      limit: 10  # Increase from 5
      interval: '1 minute'
```

**Apply without restart**:
```bash
php bin/console cache:clear --env=prod
```

**Verify new limits**:
```bash
php bin/console debug:config framework rate_limiter
```

### User Lockout Recovery

**Scenario**: User locked out due to rate limiting or MFA issues.

1. **Check user status**:
   ```sql
   SELECT id, email, status, mfa_enabled, created_at
   FROM users
   WHERE email = 'user@example.com';
   ```

2. **Clear rate limit** (if applicable):
   ```bash
   # Rate limits are stored in cache with TTL
   # Wait for interval or clear cache
   php bin/console cache:pool:clear cache.rate_limiter
   ```

3. **Reset MFA** (if needed):
   ```sql
   UPDATE users SET
     mfa_enabled = false,
     totp_secret = NULL
   WHERE email = 'user@example.com';
   ```

4. **Revoke trusted devices**:
   ```sql
   UPDATE trusted_devices SET revoked = true WHERE user_id = 'USER_ID';
   ```

### Database Connection Issues

**Scenario**: Connection pool exhaustion or database unavailable.

1. **Check connection count**:
   ```sql
   SELECT datname, count(*)
   FROM pg_stat_activity
   WHERE datname IN ('fireguard_auth', 'fireguard_main')
   GROUP BY datname;
   ```

2. **Kill idle connections**:
   ```sql
   SELECT pg_terminate_backend(pid)
   FROM pg_stat_activity
   WHERE datname IN ('fireguard_auth', 'fireguard_main')
     AND state = 'idle'
     AND state_change < NOW() - INTERVAL '10 minutes';
   ```

3. **Verify Doctrine configuration**:
   ```bash
   php bin/console debug:config doctrine dbal
   ```

### High Memory/CPU Usage

**Scenario**: Application consuming excessive resources.

1. **Check PHP processes**:
   ```bash
   ps aux | grep php | sort -k4 -rn | head -10
   ```

2. **Clear Symfony cache**:
   ```bash
   php bin/console cache:clear --env=prod
   ```

3. **Clear OPcache** (if using):
   ```bash
   php -r "opcache_reset();"
   ```

4. **Check for long-running queries**:
   ```sql
   SELECT pid, now() - pg_stat_activity.query_start AS duration, query
   FROM pg_stat_activity
   WHERE state != 'idle' AND query_start < NOW() - INTERVAL '1 minute'
   ORDER BY duration DESC;
   ```

---

## Scheduled Tasks

### Messenger Worker & Scheduler (required)

**Purpose**: asynchronous commands (intervention publication, maintenance
sweeps, recurrence materialization, automation rules) and the recurring
schedules provided by the `Maintenance`, `Intervention` and `Approval`
modules (`#[AsSchedule('maintenance')]`, `#[AsSchedule('intervention')]`,
`#[AsSchedule('approval')]`).

**Worker command** (must run permanently, e.g. under supervisor/systemd):
```bash
php bin/console messenger:consume \
  async webhook assistant \
  scheduler_maintenance scheduler_intervention scheduler_approval \
  --time-limit=3600
```

**The transport list must be kept in sync with `config/packages/messenger.yaml`.**
A transport with no consumer does not error — it silently accumulates messages
that are never processed, which is indistinguishable from "the feature does not
work" and produces no log line to investigate. Concretely, per transport:

| Transport | Omitting it means |
| --- | --- |
| `async` | Intervention publication, recurrence materialization, maintenance recompute, automation rules and CSV imports never execute |
| `webhook` | No outbound webhook is ever delivered; subscriptions look healthy and fire nothing |
| `assistant` | **Every assistant question stays `pending` forever** — the user asks, no answer and no error ever arrives |
| `scheduler_maintenance` | Inspection due dates are never recomputed; no due/overdue reminders |
| `scheduler_intervention` | Recurring interventions are never materialized |
| `scheduler_approval` | Pending four-eyes approval requests never expire; they accumulate until manually decided |

The `webhook` and `assistant` transports are deliberately isolated from `async`
so a slow or unreachable third party (or a cold model load) cannot starve
ordinary async work. `assistant` runs with `retry_strategy.max_retries: 1`
because replaying a partially streamed reply would republish its fragments —
the `assistant_messages.status` state machine is what makes a retry replace
rather than append.

Notes:
- Each `#[AsSchedule('<name>')]` provider exposes a `scheduler_<name>`
  transport that the worker must consume for its ticks to fire; consuming
  `async` alone runs the routed commands but never triggers the schedules.
- Schedules are `stateful()` (missed runs recovered from cache) and
  `lock()`-guarded, so multiple workers can consume the scheduler transports
  without duplicate ticks — **provided `LOCK_DSN` points to a shared store in
  production** (e.g. `pgsql+advisory://…` on the main database). The dev
  default `flock` only protects a single host.
- List schedules and next run dates: `php bin/console debug:scheduler`.

### Data Cleanup

**Purpose**: Remove expired tokens, sessions, OTPs, and revoked data.

**Command**:
```bash
php bin/console app:cleanup:auth-data --days=90
```

**Dry run** (preview):
```bash
php bin/console app:cleanup:auth-data --days=90 --dry-run
```

**Cron schedule** (daily at 3 AM):
```cron
0 3 * * * cd /var/www/fireguard-auth && php bin/console app:cleanup:auth-data --days=90 >> /var/log/cleanup.log 2>&1
```

**Data cleaned**:
| Dataset | Retention Rule |
|---------|----------------|
| Sessions | Revoked > X days OR inactive > X days |
| Consents | Revoked > X days |
| Access Tokens | Expired > X days |
| Refresh Tokens | Expired > X days |
| Auth Codes | Expired > X days |
| OTPs | Expired > X days |
| Trusted Devices | Expired OR revoked > X days |

### Backup Strategy

**Database backup** (daily):
```bash
pg_dump -Fc fireguard_auth > /backup/fireguard_auth_$(date +%Y%m%d).dump
pg_dump -Fc fireguard_main > /backup/fireguard_main_$(date +%Y%m%d).dump
```

**Restore**:
```bash
pg_restore -d fireguard_auth /backup/fireguard_auth_20240101.dump
pg_restore -d fireguard_main /backup/fireguard_main_20240101.dump
```

**Critical files to backup**:
- `config/jwt/private.key`
- `config/jwt/public.key`
- `.env.local` (or secrets manager reference)
- Database dump

**Retention**:
- Daily backups: 7 days
- Weekly backups: 4 weeks
- Monthly backups: 12 months

---

## Scaling Guidelines

### Horizontal Scaling

The application is **stateless** and can be horizontally scaled behind a load balancer.

**Requirements for multiple instances**:
- Shared auth database (PostgreSQL)
- Shared main database (PostgreSQL)
- Shared cache (Redis recommended for production)
- Same JWT keys on all instances
- Same encryption keys on all instances

**Load balancer configuration**:
- Health check: `GET /api/.well-known/openid-configuration`
- Session affinity: Not required (stateless)
- TLS termination: At load balancer

### Cache Configuration (Multi-Instance)

For multiple instances, use Redis instead of filesystem cache:

```yaml
# config/packages/cache.yaml
framework:
  cache:
    app: cache.adapter.redis
    default_redis_provider: 'redis://localhost:6379'
```

### Database Connection Pooling

For high traffic, use PgBouncer or similar:

```env
AUTH_DATABASE_URL="postgresql://auth_user:password@pgbouncer-auth:6432/fireguard_auth?sslmode=require"
MAIN_DATABASE_URL="postgresql://main_user:password@pgbouncer-main:6432/fireguard_main?sslmode=require"
```

---

## Troubleshooting

### Common Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| JWT signature invalid | 401 on all requests | Verify keys match, check file permissions |
| CORS errors | Browser blocks requests | Check `CORS_ALLOW_ORIGIN` regex |
| Rate limit errors | 429 responses | Review limits, check for attacks |
| Cookie not set | Refresh token missing | Check `COOKIE_SECURE`, HTTPS requirement |
| MFA not working | OTP always invalid | Check server time sync (NTP) |
| Database timeout | 500 errors | Check connection pool, query performance |

### Debug Commands

```bash
# Validate container
php bin/console lint:container

# Check routes
php bin/console debug:router | grep -E "(oauth|auth)"

# Check security configuration
php bin/console debug:config security

# Check rate limiter configuration
php bin/console debug:config framework rate_limiter

# Test auth database connection
php bin/console doctrine:query:sql "SELECT NOW()" --connection=auth

# Test main database connection
php bin/console doctrine:query:sql "SELECT NOW()" --connection=main
```

### Log Locations

| Environment | Application Log | Security Log |
|-------------|-----------------|--------------|
| Development | `var/log/dev.log` | `var/log/security.dev.log` |
| Test | `var/log/test.log` | - |
| Production | `php://stderr` (JSON) | `php://stderr` (JSON, channel: security) |

---

## Contact and Escalation

For security incidents, follow your organization's incident response procedures.

**Useful resources**:
- [SECURITY.md](./SECURITY.md) - Security configuration guide
- [ARCHITECTURE.md](./ARCHITECTURE.md) - Architecture documentation
- Module documentation in `src/<Module>/MODULE.md`




