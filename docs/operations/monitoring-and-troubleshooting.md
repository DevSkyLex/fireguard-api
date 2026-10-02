# Monitoring and troubleshooting

Production Compose bounds memory, CPU, process counts and Docker log retention.
API defaults are 768 MiB/1 CPU; consumers 384 MiB/0.75 CPU each; PostgreSQL
512 MiB/1 CPU each; Redis and Mercure 256 MiB/0.5 CPU each. The corresponding
`*_MEMORY_LIMIT` and `*_CPU_LIMIT` Compose inputs allow reviewed installation
budgets. These are per-service ceilings, not reserved capacity. Measure actual
usage, concurrency and host headroom before increasing them. Inspect OOM/restart
counts together with receiver, queue and sweep checks; HTTP availability alone
does not prove worker health. Docker logs keep five 10 MiB files per service;
`LOG_MAX_SIZE` and `LOG_MAX_FILES` allow reviewed retention overrides on API/web.
The generated API environment retains these optional budgets and their defaults.
PostgreSQL defaults to the verified PostgreSQL 16 digest in both Compose stacks.
Reviewed image overrides use `POSTGRES_IMAGE` with a compatible immutable digest;
the former `POSTGRES_VERSION` tag selector no longer selects the image. Confirm
the database major before changing that override or performing a restore exercise.

The web runtime uses the unprivileged `node` user, a 768 MiB/1 CPU default limit,
128 processes, bounded logs and a 25-second stop grace period. Its SIGTERM handler
stops accepting connections, drains accepted requests for up to 15 seconds and
closes remaining connections. `/healthz` does not depend on SSR rendering or
runtime configuration and reports unavailable once shutdown begins.

Use bounded diagnostics for the specific environment and revision. Separate application, dependency, security and asynchronous health.

**Authoritative references:** [Shared health](../../src/Shared/MODULE.md) · [Deployment](../../DEPLOYMENT.md) · [Security](../../SECURITY.md).

## Health boundaries

Check the HTTP health contract, both database connections, Redis, Mercure's internal
transport probe and worker consumption. A green Mercure probe alone does not establish
history replay after restart; DEPLOYMENT.md records the current upstream limitation.
Basic Auth and noindex apply to the documented development surfaces.

## Investigation order

1. Identify the environment, source revision, digest and failing service.
2. Compare configured identities and dependency health before restarting anything.
3. Inspect bounded health/probe history and sanitized application errors.
4. Check scoped database access, pending migrations, queue age and failed outcomes.
5. Use the owning module's retry or recovery contract and retain the evidence.

Console checks use `php -d memory_limit=1G bin/console`. `make lint` validates
wiring; schema checks name the correct entity manager. Avoid environment dumps,
bearer-token logs, private event payloads and unbounded container inspection.

## Alert policy

GeoIP is optional for authentication. Monitor the daily update/freshness-check result and
the independent revoked-session cleanup and auth-retention results. Alert on
`fireguard-maintenance` errors in the system journal, nonzero job exits and an inactive
host `cron`/`crond` service. A failed download retains the installed
file; a build older than `GEOIP_MAX_AGE_DAYS` (45 by default) suspends new enrichments.
Do not put searched IPs or locations in logs, traces, metrics or alert payloads. Use the
[GeoIP runbook](../guides/geoip-operations-and-privacy.md) for checks and recovery.
After a historical rollback, GeoIP tasks may successfully skip a valid Compose configuration
without `geoip_maintenance`. That skip must not suppress auth retention. A Compose parsing
failure is an error, even when GeoIP collection is disabled; inspect configuration privately
without dumping its resolved environment.

Set latency, error, queue age, dependency-health and capacity thresholds from the
installation's service objectives. Example operational guidance is not a configured
production alert. Authentication failures, ledger integrity failures and repeated
worker errors need a defined owner and escalation route in the local operations policy.

## Object Storage (STORAGE_DSN)

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

## Address Geocoding (GEOCODING_BASE_URL)

The Facility module's geocode endpoint
(`GET /api/organizations/{id}/facilities/geocode?address=…`) proxies address
lookups server-side through the service configured by the `GEOCODING_BASE_URL`
env var (`Facility\Infrastructure\Adapter\Geocoding\NominatimGeocodingAdapter`).

```env
# Default: the free public Nominatim (OpenStreetMap) instance — no API key.
GEOCODING_BASE_URL=https://nominatim.openstreetmap.org
```

Operational notes:

- **No key, no account** — but the public instance's usage policy applies and
  is enforced by the adapter itself: an identifying `User-Agent`
  (the adapter-owned value recorded in the installation appendix) and an absolute outbound
  ceiling of 1 request/second, serialized process-safely through the lock
  store (`LOCK_DSN`) and the shared cache pool.
- **Fail-soft**: an unreachable or erroring provider degrades to 404 on the
  endpoint (3 s timeout); facility management is never blocked by geocoding.
- Results are cached 24 h per address in the app cache pool, so repeated
  lookups do not consume the outbound budget.
- To self-host later (or to point staging at a mock), deploy a Nominatim
  instance and change only this env var — the API surface is unchanged.
- The per-user HTTP budget is the `facility_geocode` rate limiter (30/min,
  `config/packages/rate_limiter.yaml`) — see SECURITY.md.

## Application Health

**OIDC discovery check** (HTTP 200 confirms that discovery responds):

```bash
curl -s -o /dev/null -w "%{http_code}" https://api.example.com/api/.well-known/openid-configuration
```

**Expected response**: `200` with JSON containing `issuer`, `token_endpoint`, etc.

Use `GET /api/health` for the dependency-aware health endpoint used by managed
deployment. Discovery alone does not prove database, queue or worker health.

## Database Connectivity

```bash
# Auth database
php -d memory_limit=1G bin/console doctrine:query:sql "SELECT 1" --connection=auth --env=prod

# Main database
php -d memory_limit=1G bin/console doctrine:query:sql "SELECT 1" --connection=main --env=prod
```

## Cache Status

```bash
php -d memory_limit=1G bin/console cache:pool:list
php -d memory_limit=1G bin/console cache:pool:prune
```

## Container/Service Health

```bash
# Verify Symfony container
php -d memory_limit=1G bin/console debug:container --env=prod | head -20

# Check for deprecations
php -d memory_limit=1G bin/console debug:container --deprecations --env=prod
```

---

## Horizontal Scaling

The application is **stateless** and can be horizontally scaled behind a load balancer.

**Requirements for multiple instances**:

- Shared auth database (PostgreSQL)
- Shared main database (PostgreSQL)
- Shared cache (Redis recommended for production)
- Same JWT keys on all instances
- Same encryption keys on all instances

**Load balancer configuration**:

- Dependency-aware health check: `GET /api/health`
- Protocol discovery check: `GET /api/.well-known/openid-configuration`
- Session affinity: Not required (stateless)
- TLS termination: At load balancer

## Cache Configuration (Multi-Instance)

For multiple instances, use Redis instead of filesystem cache:

```yaml
# config/packages/cache.yaml
framework:
  cache:
    app: cache.adapter.redis
    default_redis_provider: 'redis://localhost:6379'
```

## Database Connection Pooling

For high traffic, use PgBouncer or similar:

```env
AUTH_DATABASE_URL="postgresql://auth_user:password@pgbouncer-auth:6432/fireguard_auth?sslmode=require"
MAIN_DATABASE_URL="postgresql://main_user:password@pgbouncer-main:6432/fireguard_main?sslmode=require"
```

---
