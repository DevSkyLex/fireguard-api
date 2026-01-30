# Audit Module

## Overview

The Audit module records security and compliance events into an immutable, append-only ledger.
It exposes read-only APIs for querying audit events with filters and pagination.
The ledger uses hash chaining (prev_hash + payload hash) to detect tampering.

## API Endpoints

| Method | Endpoint | Description | Auth |
| --- | --- | --- | --- |
| GET | `/api/audit-events` | List audit events (filtered, paginated) | `audit.read` |
| GET | `/api/audit-events/{id}` | Get audit event details | `audit.read` |

### Supported Filters

- `action`
- `actorType`
- `actorId`
- `actorEmailHash`
- `subjectType`
- `subjectId`
- `clientId`
- `tenantId`
- `ipHash`
- `from` / `to` (ISO 8601)

## Architecture

- Presentation: Api Platform resources/providers/DTOs
- Application: Use cases for record + query
- Domain: Audit event model
- Infrastructure: Doctrine persistence + hash chaining service

## Configuration

- Audit events are stored in `audit_events` and `audit_event_chains`.
- PII handling uses the same flags as security logs:
  - `SECURITY_LOG_INCLUDE_PII`
  - `SECURITY_LOG_PII_SALT`

## Testing

- Unit: `tests/Unit/Audit`
- Functional: `tests/Functional/Api` (audit endpoints)

## Error Codes

- `EntityNotFoundException` -> audit event not found
