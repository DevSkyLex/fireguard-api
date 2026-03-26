# Organization Module

## Overview

Organization manages Organizations and member-level RBAC inside each Organization.
It is isolated from authentication storage and persisted in the dedicated main database.

## Core capabilities

- Create Organizations
- Add existing users as Organization members
- Invite users by email (existing account or future account)
- Create Organization-scoped roles
- Assign roles to members
- Evaluate Organization permissions (`Organization.*`, `Organization.members.*`, `Organization.roles.*`)

## API endpoints

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/organizations` | Create a Organization and owner membership |
| GET | `/api/organizations/legal-types` | List legal types for selects |
| GET | `/api/organizations/{organizationId}/legal-profile` | Get organization legal profile |
| PUT | `/api/organizations/{organizationId}/legal-profile` | Create/update organization legal profile |
| GET | `/api/organizations` | List Organizations for current user (filter: `status`) |
| GET | `/api/organizations/{id}` | Get one Organization (requires `Organization.read`) |
| GET | `/api/organizations/{organizationId}/statistics` | Get summary Organization dashboard statistics |
| GET | `/api/organizations/{organizationId}/statistics/facilities` | Get detailed facility dashboard statistics |
| GET | `/api/organizations/{organizationId}/statistics/membership` | Get detailed member, role, and invitation dashboard statistics |
| GET | `/api/organizations/{organizationId}/statistics/equipment` | Get detailed equipment dashboard statistics |
| GET | `/api/organizations/{organizationId}/statistics/inspections` | Get detailed inspection dashboard statistics, including status, result, inspector-type, and recent-activity breakdowns |
| GET | `/api/organizations/{organizationId}/statistics/non-conformities` | Get detailed non-conformity dashboard statistics |
| POST | `/api/organizations/{organizationId}/members` | Add member and assign role(s) |
| GET | `/api/organizations/{organizationId}/members` | List Organization members |
| POST | `/api/organizations/{organizationId}/invitations` | Invite member by email |
| GET | `/api/organizations/{organizationId}/invitations` | List Organization invitations |
| POST | `/api/organizations/invitations/accept` | Accept an invitation token |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/revoke` | Revoke pending invitation |
| POST | `/api/organizations/{organizationId}/roles` | Create Organization role |
| GET | `/api/organizations/{organizationId}/roles` | List Organization roles |
| POST | `/api/organizations/{organizationId}/members/{memberId}/roles` | Assign role to member |

## Persistence

Doctrine tables are mapped in the main database:

- `Organizations`
- `Organization_members`
- `Organization_roles`
- `Organization_member_roles`
- `Organization_invitations`
- `Organization_invitation_roles`

## Architecture

- Presentation: API resources, providers, processors, DTOs
- Application: command/query use cases, ports, authorization service
- Domain: Organization/member/role models and value objects
- Infrastructure: Doctrine records, mappers, repositories

## Notes

- Auth identities stay in the auth database (`users`, tokens, sessions, etc.).
- Organization RBAC is contextual and evaluated through `OrganizationAuthorizationPort`.





