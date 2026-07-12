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
| GET | `/api/organizations` | List Organizations for current user (filter: `status`) |
| GET | `/api/organizations/{id}` | Get one Organization (requires `Organization.read`) |
| PATCH | `/api/organizations/{id}` | Update general & branding settings (name, slug, description, status). Requires `organization.settings.write` |
| POST | `/api/organizations/{organizationId}/logo` | Upload the organization logo (multipart). Requires `organization.settings.write` |
| GET | `/api/organizations/{organizationId}/logo.webp` | Stream the organization logo (public) |
| GET | `/api/organizations/{organizationId}/me` | Get the authenticated active member profile with resolved roles and effective permissions |
| GET | `/api/organizations/{organizationId}/dashboard` | Get lightweight Organization overview KPIs for cards. `overview`, `alerts`, and non-`period*` KPIs are snapshots at `generatedAt`; `comparison` and `period*` KPIs follow `from`/`to` (filters: `from`, `to`, `compare`, `timezone`). Use dedicated `/dashboard/trends/*` endpoints for chart series. Requires `organization.dashboard.read` plus members/roles/facilities/equipment/inspection read permissions. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/inspections` | Get the inspections-performed series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters. Requires `organization.inspection.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/equipment-created` | Get the equipment-created series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus `equipmentType` and `equipmentStatus`. Requires `organization.equipment.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/facilities-created` | Get the facilities-created series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus `facilityType`. Requires `organization.facilities.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/non-conformities-opened` | Get the non-conformities-opened series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters. Requires `organization.inspection.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/non-conformities-resolved` | Get the non-conformities-resolved series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters. Requires `organization.inspection.read`. |
| POST | `/api/organizations/{organizationId}/members` | Add member and assign role(s) |
| GET | `/api/organizations/{organizationId}/members` | List Organization members |
| POST | `/api/organizations/{organizationId}/invitations` | Invite member by email |
| GET | `/api/organizations/{organizationId}/invitations` | List Organization invitations |
| GET | `/api/organizations/invitations/{token}/preview` | Public preview of an invitation by token (organization, inviter, invited email, status, expiry) |
| POST | `/api/organizations/invitations/accept` | Accept an invitation token |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/revoke` | Revoke pending invitation |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/resend` | Regenerate token, reset expiry and re-send the invitation email (returns a fresh accept link) |
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





