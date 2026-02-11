# Organization Module

## Overview

Organization manages Organizations and member-level RBAC inside each Organization.
It is isolated from authentication storage and persisted in the dedicated main database.

## Core capabilities

- Create Organizations
- Add existing users as Organization members
- Create Organization-scoped roles
- Assign roles to members
- Evaluate Organization permissions (`Organization.*`, `Organization.members.*`, `Organization.roles.*`)

## API endpoints

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/organizations` | Create a Organization and owner membership |
| GET | `/api/organizations` | List Organizations for current user |
| GET | `/api/organizations/{id}` | Get one Organization (requires `Organization.read`) |
| POST | `/api/organizations/{organizationId}/members` | Add member and assign role(s) |
| GET | `/api/organizations/{organizationId}/members` | List Organization members |
| POST | `/api/organizations/{organizationId}/roles` | Create Organization role |
| GET | `/api/organizations/{organizationId}/roles` | List Organization roles |
| POST | `/api/organizations/{organizationId}/members/{memberId}/roles` | Assign role to member |

## Persistence

Doctrine tables are mapped in the main database:

- `Organizations`
- `Organization_members`
- `Organization_roles`
- `Organization_member_roles`

## Architecture

- Presentation: API resources, providers, processors, DTOs
- Application: command/query use cases, ports, authorization service
- Domain: Organization/member/role models and value objects
- Infrastructure: Doctrine records, mappers, repositories

## Notes

- Auth identities stay in the auth database (`users`, tokens, sessions, etc.).
- Organization RBAC is contextual and evaluated through `OrganizationAuthorizationPort`.





