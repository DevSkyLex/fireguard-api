# Operations Guide

**Reading guide:** [Documentation index](docs/README.md) · [Related guide](docs/operations/monitoring-and-troubleshooting.md).

This entry point preserves operational links while detailed procedures live in
topic guides. [DEPLOYMENT.md](DEPLOYMENT.md) owns managed delivery; the
[installation appendix](docs/operations/current-installation.md) records identities.
Every command targets a deliberately selected environment. Auth and main keep
separate connections, migrations, backups and recovery decisions.

## Table of Contents

See the [documentation index](docs/README.md) and the topic links below.

## Deployment

See the [deployment](docs/operations/migrations-and-backups.md) procedure.

### Prerequisites

See the [prerequisites](docs/operations/migrations-and-backups.md) procedure.

### Environment Setup

See the [environment setup](docs/guides/local-development.md) procedure.

### Database Migrations

See the [database migrations](docs/operations/migrations-and-backups.md) procedure.

### Object Storage (STORAGE_DSN)

See the [object storage (storage_dsn)](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Address Geocoding (GEOCODING_BASE_URL)

See the [address geocoding (geocoding_base_url)](docs/operations/monitoring-and-troubleshooting.md) procedure.

### RBAC Permission Sync

See the [rbac permission sync](docs/operations/security-runbooks.md) procedure.

### Test Coverage (HTML)

See the [test coverage (html)](docs/guides/testing.md) procedure.

### JWT Key Generation

See the [jwt key generation](docs/operations/security-runbooks.md) procedure.

### Deployment Checklist

See the [deployment checklist](docs/operations/security-runbooks.md) procedure.

## Health Checks

See the [health checks](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Application Health

See the [application health](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Database Connectivity

See the [database connectivity](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Cache Status

See the [cache status](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Container/Service Health

See the [container/service health](docs/operations/monitoring-and-troubleshooting.md) procedure.

## Monitoring

See the [monitoring](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Key Metrics

See the [key metrics](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Log Analysis

See the [log analysis](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Alerting Thresholds

See the [alerting thresholds](docs/operations/monitoring-and-troubleshooting.md) procedure.

## Runbooks

See the [runbooks](docs/operations/security-runbooks.md) procedure.

### Token Revocation (Emergency)

See the [token revocation (emergency)](docs/operations/security-runbooks.md) procedure.

### JWT Key Rotation

See the [jwt key rotation](docs/operations/security-runbooks.md) procedure.

### Rate Limit Adjustment

See the [rate limit adjustment](docs/operations/security-runbooks.md) procedure.

### User Lockout Recovery

See the [user lockout recovery](docs/operations/security-runbooks.md) procedure.

### Database Connection Issues

See the [database connection issues](docs/operations/security-runbooks.md) procedure.

### High Memory/CPU Usage

See the [high memory/cpu usage](docs/operations/security-runbooks.md) procedure.

## Scheduled Tasks

See the [scheduled tasks](docs/operations/workers-and-scheduler.md) procedure.

### Messenger Worker & Scheduler (required)

Every configured queue and scheduler receiver requires a supervised consumer.
The following combined-worker example covers the current transport set. Managed
Compose deployments split these receivers into async, webhook, assistant and
scheduler services. Maintain that isolation and the same complete receiver coverage.

```sh
php -d memory_limit=1G bin/console messenger:consume \
  main_outbox async webhook assistant \
  scheduler_maintenance scheduler_intervention scheduler_approval \
  scheduler_inspection scheduler_organization \
  --time-limit=3600
```

This is the canonical worker transport list, checked against Messenger configuration
and schedule providers by the architecture tests. Use a supervisor/restart policy
for bounded worker processes. See the [workers and scheduler guide](docs/operations/workers-and-scheduler.md#messenger-worker--scheduler-required)
for transport setup, monitoring and recovery.

### Data Cleanup

See the [data cleanup](docs/operations/workers-and-scheduler.md) procedure.

### Backup Strategy

See the [backup strategy](docs/operations/migrations-and-backups.md) procedure.

## Scaling Guidelines

See the [scaling guidelines](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Horizontal Scaling

See the [horizontal scaling](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Cache Configuration (Multi-Instance)

See the [cache configuration (multi-instance)](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Database Connection Pooling

See the [database connection pooling](docs/operations/monitoring-and-troubleshooting.md) procedure.

## Troubleshooting

See the [troubleshooting](docs/operations/monitoring-and-troubleshooting.md) procedure.

### Interactive session security rollout

See the [interactive session security rollout](docs/operations/security-runbooks.md) procedure.

### Common Issues

See the [common issues](docs/operations/security-runbooks.md) procedure.

### Debug Commands

See the [debug commands](docs/operations/security-runbooks.md) procedure.

### Log Locations

See the [log locations](docs/operations/security-runbooks.md) procedure.

## Contact and Escalation

See the [contact and escalation](docs/operations/security-runbooks.md) procedure.

## Maintenance evaluation rollout

See the [maintenance evaluation rollout](docs/operations/migrations-and-backups.md) procedure.

## Import simulation confirmation rollout

See the [import simulation confirmation rollout](docs/operations/migrations-and-backups.md) procedure.
