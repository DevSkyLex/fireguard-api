# Fireguard API

Symfony/API Platform backend for FireGuard fire-equipment park management,
organization access, controls, maintenance and field interventions. The [web application](https://github.com/DevSkyLex/fireguard-web)
is a separate repository. Dependency versions live in [composer.json](composer.json)
and its lockfile.

## Table of Contents

- [Getting started](#getting-started)
- [API surface](#api-surface)
- [Architecture](#architecture)
- [Testing](#testing)
- [Code Quality](#code-quality)
- [Operations](#operations)

## Overview

The API uses four-layer modules and two independent PostgreSQL databases:
`auth` for identity/security and `main` for business data. The mapping in
[Doctrine configuration](config/packages/doctrine.yaml) is authoritative.

## Features

Identity, OAuth/OIDC, organizations and billing, facilities and equipment,
internal customers, inspections, independent preventive operations, traceable
replacement, repair requests, interventions, quantitative parts, procurement,
private costs and economic reports, versioned maintenance exports, workload,
messaging, notifications,
assistant, calendar, approvals, automation, imports and webhooks. The
[documentation index](docs/README.md) links every module contract.

## API surface

[openapi.json](openapi.json) describes the generated HTTP schema. Each module's
`MODULE.md` owns its contextual permissions, flows, errors and public behavior.

### Auth

See [Auth](src/Auth/MODULE.md) and the [authentication guide](docs/guides/authentication.md).

### OAuth2 and OIDC

See [OAuth](src/OAuth/MODULE.md).

### Discovery

See [OAuth discovery](src/OAuth/MODULE.md) and [Shared health](src/Shared/MODULE.md).

### Administration

See [User](src/User/MODULE.md), [Authorization](src/Authorization/MODULE.md),
[Tenant](src/Tenant/MODULE.md), [Session](src/Session/MODULE.md) and
[TrustedDevice](src/TrustedDevice/MODULE.md).

### Organizations and billing

See [Organization](src/Organization/MODULE.md) and [Billing](src/Billing/MODULE.md).

### Field operations

See [Facility](src/Facility/MODULE.md), [Equipment](src/Equipment/MODULE.md),
[Inspection](src/Inspection/MODULE.md), [Maintenance](src/Maintenance/MODULE.md)
and [Compliance](src/Compliance/MODULE.md). Internal customers and optional site
ownership are described by [Customer](src/Customer/MODULE.md).

### Maintenance resources and economic reporting

See [ServiceRequest](src/ServiceRequest/MODULE.md), [Inventory](src/Inventory/MODULE.md),
[Procurement](src/Procurement/MODULE.md), [MaintenanceCost](src/MaintenanceCost/MODULE.md)
and [MaintenanceExport](src/MaintenanceExport/MODULE.md). Internal financial facts
require dedicated permissions and stay outside ordinary customer reports. Retained
exports distinguish generation from confirmed import; corrections append linked
adjustments. Commercial invoicing remains in the external ERP.

### Interventions, approval and automation

See [Intervention](src/Intervention/MODULE.md), [Approval](src/Approval/MODULE.md),
[Automation](src/Automation/MODULE.md) and [Workload](src/Workload/MODULE.md).

### Collaboration and assistant

See [Messaging](src/Messaging/MODULE.md), [Notification](src/Notification/MODULE.md)
and [Assistant](src/Assistant/MODULE.md).

### Calendar, import and webhooks

See [Calendar](src/Calendar/MODULE.md), [Import](src/Import/MODULE.md)
and [Webhook](src/Webhook/MODULE.md).

### Onboarding

See [Onboarding](src/Onboarding/MODULE.md).

### Audit

See [Audit](src/Audit/MODULE.md) and [SECURITY.md](SECURITY.md).

## Flows

### OAuth2 and OIDC core flow

The [authentication guide](docs/guides/authentication.md) explains interactive
login and OAuth code exchange separately, with their session and token boundaries.

## Architecture

[ARCHITECTURE.md](ARCHITECTURE.md) defines module layout, inward dependencies,
ports, use cases and documentation requirements. The
[system overview](docs/architecture/system-overview.md) shows runtime services.
Dependency arrows and runtime request arrows describe different relationships.

## Project layout

```text
config/       # framework and module wiring
migrations/   # independent auth/main migration histories
src/<Module>/ # Domain, Application, Infrastructure and Presentation
tests/        # architecture, unit, integration, functional and E2E checks
docs/         # development guides and operations procedures
```

The module list is available in the documentation index. `Shared` owns generic
infrastructure contracts; it is not a business module.

## Configuration

Framework configuration lives in `config/packages/`, module bindings in
`config/modules/`. Configure local environment overrides privately; never commit
tokens, key material or production connection strings. See the
[development guide](docs/guides/local-development.md) and [SECURITY.md](SECURITY.md).

## Requirements

- PHP 8.4 or newer, Composer and Make.
- PostgreSQL for both databases, matching the repository's SQL and test contracts.
- Docker Compose for the documented local stack.
- Node.js 22 only when running the isolated documentation tools.

## Getting started

```sh
composer install
make docker-up
```

Configure local overrides and apply both migration histories before using real
data. Fixture resets are destructive operations with their own explicit procedure;
follow [local development](docs/guides/local-development.md).

## Development

### Using Docker (Recommended)

The local Compose stack provides the application, both PostgreSQL databases,
Redis and Mailpit. Its ports and service definitions are in [compose.yaml](compose.yaml).

### Common Make Targets

[Makefile](Makefile) is the command inventory. Use `make migrate-all` for both
histories, `make lint` for wiring, and the test commands below. The
development guide describes local fixture reset and credential handling.

## Testing

Prepare test templates with `make test-db`, then run `make phpunit-fast` or
`make phpunit-parallel`. Each run uses isolated clones. Development seeding is
a separate operation. [Testing](docs/guides/testing.md) explains focused runs,
denial paths and database ownership; [COVERAGE.md](COVERAGE.md) defines acceptance.

## Code Quality

### Quality feedback: editor, hooks, CI

The project formatter, Git hooks and CI share repository configuration. Hooks
provide local feedback; CI supplies revision-specific quality evidence.
`make cs-lint`, `make phpstan`, `make deptrac`, `make lint`, `make openapi-check`
and `make schema-check` cover distinct risks. `make test` composes the application gates.

### SonarQube

See [SONARQUBE.md](SONARQUBE.md) for branch-specific projects and readiness.

### CI/CD

CI validates changes; delivery verifies the exact source revision and SonarQube
gate before applying an immutable image with Ansible. See [DEPLOYMENT.md](DEPLOYMENT.md).

## Operations

[OPERATIONS.md](OPERATIONS.md) indexes health checks, backups, workers and incident
procedures. Current installation values live in the
[installation appendix](docs/operations/current-installation.md).

## Security

[SECURITY.md](SECURITY.md) defines security invariants and reporting guidance.

## License

Proprietary. Distribution and use follow the project's licensing agreement.
