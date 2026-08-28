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

## API Endpoints

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/organizations` | Create a Organization and owner membership |
| GET | `/api/organizations` | List Organizations for current user (filter: `status`). Each item also carries the CALLER's membership info: `isOwner` (caller vs `ownerUserId`) and `roles` (`[{id, label}]`, the caller's assigned org-role labels, `[]` when none) — see Notes |
| GET | `/api/organizations/{id}` | Get one Organization (requires `Organization.read`). Carries the same caller-membership `isOwner`/`roles` as the list above — see Notes |
| DELETE | `/api/organizations/{id}` | Archive the organization (reversible soft delete — see Notes; **not** a permanent removal). Requires `organization.delete` plus the danger-zone confirmation: a `slug` query parameter matching the organization's current slug (case-insensitive, trimmed). Missing or mismatched confirmation → HTTP 422, nothing archived. Idempotent when already archived, provided the confirmation is still correct |
| POST | `/api/organizations/{id}/suspend` | Suspend the organization as an explicit, dedicated action — coexists with (does not replace) the legacy `isActive: false` toggle on the settings PATCH below. Requires `organization.settings.write`, the SAME permission the legacy toggle already requires — see Notes (P2.5). 409 when archived (restore it first). Idempotent when already suspended. Returns the refreshed `OrganizationOutput`, caller-membership `isOwner`/`roles` included |
| POST | `/api/organizations/{id}/restore` | Restore the organization to ACTIVE from SUSPENDED or ARCHIVED, as an explicit, dedicated action — coexists with (does not replace) the legacy `isActive: true` toggle. Requires `organization.settings.write` — see Notes (P2.5). Idempotent when already active. Returns the refreshed `OrganizationOutput`, caller-membership `isOwner`/`roles` included |
| POST | `/api/organizations/{id}/transfer-ownership` | Transfer ownership to another active member. The caller must be an ACTIVE member of the organization — a stranger gets the same 404 a nonexistent organization id would produce, before the slug or owner checks ever run (closes an existence/slug oracle — see Notes). An active member who is not the organization's CURRENT owner gets 403 instead — RBAC-independent, no permission grants the right to give away someone else's ownership. Requires the same danger-zone `slug` confirmation as DELETE, now in the request body (missing/mismatched → 422), checked only once the caller is confirmed to be the owner. Target must be an active member (404 otherwise); an archived organization or a target already owning it → 409. The new owner is granted the system `admin` role if missing AND the acting (previous) owner still holds every permission that role carries (`OrganizationPermissionGrantGuardPort`, the same no-privilege-escalation check every other role-granting surface applies); a missing role, a guard refusal, or any other failure while granting it is logged and skipped — it never fails the already-committed transfer. Returns the refreshed `OrganizationOutput` — see Notes. `isOwner` reflects the POST-transfer truth for the acting caller (`false`, since ownership just moved away from them) |
| PATCH | `/api/organizations/{id}` | Update general & branding settings (name, slug, description, status), the legal profile (`country`, `legalType`, `legalName`, `registrationNumber`, `vatNumber` — see below), plus the structured sections: `notifications`, `regional`, `compliance` (non-conformity SLA days per severity, inspection periodicity per equipment type, reminder window — map entries set to `null` revert to the catalog default from `OrganizationComplianceDefaults`; only customizations are persisted, effective values are resolved on read), `automation` (explicit opt-in toggles, e.g. `autoCreateInterventionOnCriticalNc`) , `approval` (R17 four-eyes policy: `actionRules` per gated action type — `enabled`/`minApproverRole`/`minSeverity`, `null` entry reverts to disabled —, `allowSelfApproval`, `approvalTtlDays`; every action type defaults to disabled) and `assistant` (AI-assistant policy: `enabled`, `model`, `temperature`, `includeBusinessContext`; disabled by default). Periodicity keys are validated against the Equipment catalog via `EquipmentTypeCatalogPort`; `approval.actionRules` keys are validated against the Approval catalog via `ApprovalActionTypeCatalogPort`. Requires `organization.settings.write`. Returns the refreshed `OrganizationOutput`, caller-membership `isOwner`/`roles` included |
| GET | `/api/organizations/legal-types` | Reference catalog of organization legal entity type values/labels for the Legal profile settings tab select |

Removed 2026-08-20: `GET /api/organizations/statuses` and
`GET /api/organizations/invitation-statuses` (unconsumed reference catalogs; the
frontend's localized typed registries are the source of these values).
`/api/organizations/legal-types` is kept — it has a real frontend consumer.
| POST | `/api/organizations/{organizationId}/logo` | Upload the organization logo (multipart). Requires `organization.settings.write` |
| DELETE | `/api/organizations/{organizationId}/logo` | Remove the organization logo. Requires `organization.settings.write` (same permission as upload). 409 when archived. Idempotent when the organization already has no logo — see Notes (P2.5) |
| GET | `/api/organizations/{organizationId}/logo.webp` | Stream the organization logo (public) |
| GET | `/api/organizations/{organizationId}/me` | Get the authenticated active member profile with resolved roles and effective permissions |
| GET | `/api/organizations/{organizationId}/dashboard` | Get lightweight Organization overview KPIs for cards, plus `trends` (per-KPI sparkline running-total series for facilities/members/equipment/inspections) and `recentInterventions` (the 5 most recently updated field interventions, org-scoped, gated by `organization.interventions.read`). `overview`, `alerts`, and non-`period*` KPIs are snapshots at `generatedAt`; `comparison` and `period*` KPIs follow `from`/`to` (filters: `from`, `to`, `compare`, `timezone`). `overview.nonConformities.severityLow`/`severityMedium`/`severityHigh`/`severityCritical` add an org-wide, ALWAYS-unfiltered by-severity breakdown across every status — see Notes (L3.10). Use dedicated `/dashboard/trends/*` endpoints for full chart series with custom granularity. Requires `organization.dashboard.read` plus members/roles/facilities/equipment/inspection read permissions. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/inspections` | Get the inspections-performed series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters. Requires `organization.inspection.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/equipment-created` | Get the equipment-created series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus `equipmentType` and `equipmentStatus`. Requires `organization.equipment.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/facilities-created` | Get the facilities-created series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus `facilityType`. Requires `organization.facilities.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/non-conformities-opened` | Get the non-conformities-opened series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus an optional `metrics` filter (e.g. `metrics=non_conformities_resolved`) that adds the resolved series to the response's `seriesByMetric` map, sharing this call's resolved period/timezone/granularity — see Notes (L3.9). Requires `organization.inspection.read` per requested metric. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/non-conformities-resolved` | Get the non-conformities-resolved series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus the same optional `metrics` combining filter (`metrics=non_conformities_opened`) — see Notes (L3.9). Requires `organization.inspection.read` per requested metric. |
| GET | `/api/organizations/{organizationId}/navigation-counters` | Get lightweight sidebar badge counters: `openInterventions` (excludes `published`/`abandoned`), `openNonConformities` (`open` + `in_progress`) and `submittedInterventions` (status `submitted`, the "to review" badge). Caller must be an ACTIVE organization member; each counter individually falls back to `0` (never a 403) without the underlying `organization.interventions.read` / `organization.inspection.read` / `organization.interventions.review` permission — see Notes (L3.11) |
| GET | `/api/organizations/{organizationId}/audit-events` | List the organization's slice of the audit ledger (activity feed), newest first, paginated (filters: `action`, `from`, `to`; `itemsPerPage` capped at 100). Requires `organization.audit.read` (admin-granted — not part of the member system role; admins hold it via `organization.*`). Reduced payload: no actor email, IP, user agent or chain internals, metadata filtered by the Audit module's per-action allowlist, and an actor who is not a member of this organization is never named — see Notes (P2.6) |
| GET | `/api/organizations/{organizationId}/audit-events/export` | Stream the same slice as CSV, same filters. Requires `organization.audit.export`, **not** `organization.audit.read`: reading keeps the data inside the product, exporting takes a file out, and someone entitled to look is not automatically entitled to walk away with a copy. The organization comes from the URI and is not a filter the caller can widen — unlike the platform `/audit-events/export`, which composes its criteria from the request and is therefore reserved to platform operators. Columns match the read payload exactly: no actor email, IP, user agent or chain internals. Capped at the same 50 000 rows as the platform export, answered as 422 **before** the response starts streaming |
| POST | `/api/organizations/{organizationId}/members` | Add member and assign role(s) |
| GET | `/api/organizations/{organizationId}/members` | List Organization members (each item carries `isOwner`, computed against the organization's `ownerUserId`). Filters: `search` (matched against the member's user identifier at SQL level), `status` (`active`/`inactive`/`all`, 422 on any other value), `roleId`; sort via `order[joinedAt\|displayName]=asc\|desc` (default `order[joinedAt]=asc`). All filtering/sorting/pagination is pushed down to the repository — see Notes (P2.3) |
| GET | `/api/organizations/{organizationId}/members/{memberId}` | Get a single organization member. Requires `organization.members.read`. 404 when the member does not exist, and 404 (not a leaked 403) when it belongs to a different organization than `{organizationId}` — see Notes (P2.3) |
| POST | `/api/organizations/{organizationId}/members/{memberId}/reactivate` | Reactivate a previously deactivated (removed) member. Requires `organization.members.manage`. 404 unknown member or member in another organization; 409 already active, organization archived, or the plan's member cap reached — see Notes (P2.3, quota) |
| PUT | `/api/organizations/{organizationId}/members/{memberId}/roles` | Replace a member's entire role set in one call (full replacement, not a delta — an empty `roleIds` clears every role). Requires `organization.roles.manage`, mirroring the unit assign/remove-role operations. Roles being granted go through the privilege-escalation guard (403); roles being revoked go through the last-administrator lockout guard (409); an unknown role id is 404, exactly like `POST .../members/{memberId}/roles` below — see Notes (P2.3) |
| DELETE | `/api/organizations/{organizationId}/members/me` | Leave the organization (self-removal by the authenticated user). No permission required beyond an active membership. The organization's current owner cannot leave (409 — transfer ownership first) and leaving is refused when it would strip the organization of its last active administrator (409). 404 when the caller is not an active member. Registered ahead of, and disambiguated by UUID `requirements` from, `DELETE /members/{memberId}` below |
| POST | `/api/organizations/{organizationId}/invitations` | Invite member by email |
| GET | `/api/organizations/{organizationId}/invitations` | List Organization invitations |
| GET | `/api/organizations/invitations/{token}/preview` | Public preview of an invitation by token (organization, inviter, masked invited email, status, expiry, `roleNames`). Unauthenticated and rate-limited per IP. `roleNames` carries the display names of the roles the invitation grants, resolved within the invitation's own organization — names only, never role ids or permissions |
| POST | `/api/organizations/invitations/accept` | Accept an invitation token. Rate-limited per user (`limiter.invitation_accept`), the third of the trio alongside `preview` (per IP) and `resend` (per user) |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/revoke` | Revoke pending invitation |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/resend` | Regenerate token, reset expiry and re-send the invitation email (returns a fresh accept link) |
| POST | `/api/organizations/{organizationId}/roles` | Create Organization role |
| GET | `/api/organizations/{organizationId}/roles` | List Organization roles (each item carries `memberCount`, the number of ACTIVE members currently assigned). Real pagination (`page`/`itemsPerPage`, default 30) — `totalItems` reflects the count AFTER `search` filtering, not the organization's raw role count. Supports `search` (role name) and `order[name\|isSystem\|createdAt]=asc\|desc` (default `order[name]=asc`) — see Notes (P2.4) |
| GET | `/api/organizations/{organizationId}/roles/{roleId}` | Get a single organization role, including `memberCount`. Requires `organization.roles.read`. 404 unknown role or role in another organization — see Notes (P2.4) |
| PATCH | `/api/organizations/{organizationId}/roles/{roleId}` | Update a custom role's permissions/description, and optionally rename it (`name`, same 3-50 char lowercase-alphanumeric-or-underscore constraint as create). Requires `organization.roles.manage`. A duplicate name or a rename attempt on a system role both map to HTTP 400 (`InvalidArgumentException`, mirroring how `POST .../roles` maps its own duplicate-name refusal) — see Notes (P2.4) |
| DELETE | `/api/organizations/{organizationId}/roles/{roleId}` | Permanently delete a custom role (system roles cannot be deleted). Requires `organization.roles.manage`, guarded by the last-administrator lockout (409) |
| POST | `/api/organizations/{organizationId}/members/{memberId}/roles` | Assign role to member (single-role add; see `PUT` above for the bulk full-replacement variant) |
| POST | `/api/organizations/{organizationId}/teams` | Create a team (requires `organization.teams.write`) |
| GET | `/api/organizations/{organizationId}/teams` | List teams (requires `organization.teams.read`) |
| GET | `/api/organizations/{organizationId}/teams/{teamId}` | Get a single team (requires `organization.teams.read`) |
| PATCH | `/api/organizations/{organizationId}/teams/{teamId}` | Rename/redescribe a team (requires `organization.teams.write`) |
| DELETE | `/api/organizations/{organizationId}/teams/{teamId}` | Delete a team (requires `organization.teams.manage`) |
| POST | `/api/organizations/{organizationId}/teams/{teamId}/members` | Add an active organization member to a team (requires `organization.teams.write`) |
| DELETE | `/api/organizations/{organizationId}/teams/{teamId}/members/{memberId}` | Remove a member from a team (requires `organization.teams.write`) |
| GET | `/api/organizations/{organizationId}/teams/{teamId}/members` | List team members (requires `organization.teams.read`) |
| GET | `/api/plans` | List the subscription plan catalog (`ROLE_USER`; administrators see every plan, regular users see selectable plans only). Each item carries `limits`/`quotas` (entitlement) plus `tagline`/`perks` (marketing display copy) — see Notes (L3.8) |
| GET | `/api/plans/{id}` | Get a single subscription plan by id (`ROLE_USER`) |
| POST | `/api/plans` | Create a subscription plan (`ROLE_ADMIN`) |
| PATCH | `/api/plans/{id}` | Update a subscription plan (`ROLE_ADMIN`) |
| DELETE | `/api/plans/{id}` | Delete a subscription plan; the default plan cannot be deleted (`ROLE_ADMIN`) |

## Architecture

- Presentation: API resources, providers, processors, DTOs
- Application: command/query use cases, ports, authorization service
- Domain: Organization/member/role models and value objects
- Infrastructure: Doctrine records, mappers, repositories

Cross-module dependencies, and the contract each goes through:

| Direction | Port | Contract type | Why |
| --- | --- | --- | --- |
| consumed | `Audit\Application\Port\Inbound\OrganizationAuditFeedPort` | `Audit\…\Contract\OrganizationAuditEntry` | the activity feed — Audit publishes a scoped, reduced read rather than lending its ledger repository; see Notes (P2.6) |
| published | `Organization\Application\Port\Inbound\TeamDirectoryPort` | `…\Contract\Team\TeamMembershipSnapshot` | lets Intervention (and later Messaging) resolve a team's active membership without touching this module's Domain |
| published | `Organization\Application\Port\Inbound\OrganizationAuthorizationPort` | `…\Contract\Authorization\OrganizationAccessDecision` | the permission check every other module's org-scoped endpoint runs |
| published | `Organization\Application\Port\Inbound\MemberInvitationProvisioningPort` | `…\Contract\Provisioning\{ProvisionMemberInvitationRequest, ProvisionMemberInvitationResult, ProvisionOutcome}` | lets Import's bulk CSV member import provision invitations through the existing `InviteOrganizationMemberHandler` (member-cap quota and conflict rules intact). `MemberInvitationProvisioningService` resolves role *names* to ids (`OrganizationRoleRepositoryPort`), validates the email, and translates every failure into a typed outcome — `CREATED`\|`QUOTA_EXCEEDED`\|`ALREADY_MEMBER`\|`ALREADY_INVITED`\|`UNKNOWN_ROLE`\|`INVALID` (the two conflicts distinguished via `OrganizationMembershipConflictException::conflict()`, a discriminator added for exactly this). A `dryRun` request validates the email and role names and returns without dispatching — nothing persisted, no email sent, no quota projection (deliberately lighter than Equipment/Facility's dry runs; see `src/Import/MODULE.md`) |
| published | `Organization\Application\Port\Inbound\OrganizationDocumentBrandingPort` | `…\Contract\Document\OrganizationDocumentBranding` | document (PDF) branding for Compliance and Intervention exports: display name, stored logo inlined as a base64 `data:` URI (implemented by `Infrastructure\Adapter\Document\OrganizationDocumentBrandingAdapter` reading `FileStoragePort`), legal identity (legal name, registration number, VAT), regional settings (timezone, locale, `dateFormat`). Never throws: a missing organization or logo degrades to defaults |

### Weekly digest (recurring email recap)

The weekly digest lives **in this module on purpose**: it is a cross-module
aggregate (interventions, maintenance, non-conformities) whose subject is the
*organization* — the same shape as the organization dashboard, which already
pulls those numbers through this module's outbound statistics ports. Housing it
in any producing module would privilege one section over the others and force
that module to learn about the two siblings; Organization already owns the
member directory, the authorization service, and the notification policy the
digest needs.

- **Schedule**: `Infrastructure\Scheduler\OrganizationScheduleProvider`
  (`#[AsSchedule('organization')]`, transport `scheduler_organization`) fires
  `SendWeeklyDigestsCommand` every **Monday 06:00 UTC** (an anchored 1-week
  periodical trigger — the cron-expression package is not a dependency),
  stateful + lock-guarded like the other sweep schedules. See `OPERATIONS.md`.
- **Use case**: `Application\UseCase\Command\Sweep\SendWeeklyDigests` pages
  through active organizations (`OrganizationRepositoryPort::pageActiveIds`)
  and aggregates, per organization: overdue interventions
  (`InterventionStatisticsPort::countOverview` + `findOverdueInterventions`),
  maintenance deadlines due within 7 days plus overdue ones
  (`MaintenanceStatisticsPort`, adapter in the Maintenance module), and
  unresolved non-conformities incl. SLA-breached ones
  (`NonConformityStatisticsPort::countNonConformitiesByStatus`,
  `countSlaBreachedNonConformities`, `findOpenNonConformities`). Detail lines
  are capped at 5 per section; the email says "and N more".
- **Silence at zero**: an organization whose counters are all zero gets **no
  email**. This is deliberate — the digest reports what needs attention, not
  that nothing does.
- **Toggles**: the org-level `weeklyDigest` category toggle (new flag on
  `OrganizationNotificationSettings`, PATCH `/api/organizations/{id}` →
  `notifications.weeklyDigest`) and the org-level `emailEnabled` channel toggle
  both gate the sweep before any data is read. Each recipient's own per-channel
  preference for the `organization` category is then enforced by the
  Notification module (the type is `organization.weekly_digest`).
- **Recipients**: `OrganizationWeeklyDigestRecipientResolver` — the active
  members whose effective permissions grant `organization.settings.write`
  (directly or through a wildcard), i.e. the people who administer the
  organization and can turn the digest off. Mirrors the resolver pattern of the
  maintenance-reminder and NC-SLA sweeps, adapted to the administration
  permission.
- **Delivery**: email only, by design — a periodic summary is not a real-time
  event, so no Mercure/in-app duplicate. `OrganizationWeeklyDigestNotifier`
  localizes per recipient (en/fr/es, clamped like the invitation email),
  renders `templates/notification/email/organization_weekly_digest.html.twig`
  (keys under `digest.` in `translations/emails.*.yaml`), and deep-links to
  `{frontend}/organizations/{id}` (the org dashboard). Best-effort per
  recipient and per organization; failures log and never fail the sweep.
- **No domain/audit event**: sending a digest changes no business state — it is
  a notification fan-out, exactly like the NC-SLA and maintenance-reminder
  sweeps, which dispatch none either. The Notification module persists each
  sent notification, which is the delivery trace.

### `OrganizationAuthorizationPort` — three ways to ask

| Method | Answers | Use when |
| --- | --- | --- |
| `hasPermission()` | `bool` | the caller has already established the requester is in scope, or the endpoint has no record whose existence a denial could reveal |
| `resolveAccess()` | `OrganizationAccessDecision`: `GRANTED` / `MISSING_PERMISSION` / `OUTSIDE_SCOPE` | the default for an org-scoped record — a consumer needs 404 for out-of-scope and 403 for unentitled |
| `isMemberOf()` | `bool` | the scope half alone, when the required permission cannot be named until after a call that may itself throw |

`resolveAccess()` exists because a boolean denial is an existence oracle:
a consumer that maps it to 403 tells a caller from another organization that
a record they may not read is real. Consumers therefore map `OUTSIDE_SCOPE`
to whatever 404 an unknown identifier already produces, and only
`MISSING_PERMISSION` to 403. `Intervention`'s MODULE.md documents the
convention in full under *Scope versus entitlement*.

"In scope" means an **active** membership, resolved by
`OrganizationMemberRepositoryPort::hasActiveMembership()` — the same
`isActive` predicate `getPermissionNamesForUserInOrganization()` filters on,
so the two can never disagree. An empty permission list cannot stand in for
it: an active member holding a role with no permissions also resolves to
none. The membership query only runs when the permission is not granted, so
the authorized path costs exactly what `hasPermission()` costs, and the
answer is memoized per request alongside the permission cache.

## Persistence

Doctrine tables are mapped in the main database:

- `Organizations`
- `Organization_members`
- `Organization_roles`
- `Organization_member_roles`
- `Organization_invitations`
- `Organization_invitation_roles`
- `teams`
- `team_members`

## Seed fixtures

`Organization\Infrastructure\DataFixtures\OrganizationFixtures` (group
`organization`, tagged `app.seed_fixture.main`) seeds the flagship
"Fireguard Seed Organization" — the only tenant every other module's
fixtures (Facility, Equipment, Inspection, Maintenance, Intervention) attach
to — plus `SECONDARY_ORGANIZATION_SEEDS`: four lightweight, independent
tenants so the organization switcher and any platform-level listing have
more than one row, and the less common `OrganizationStatus`/plan/legal-type
values are represented too:

| Organization | Status | Plan | Members |
| --- | --- | --- | --- |
| Nova Sécurité Incendie | active | Pro | owner + 2 (cross-org with the main org's bulk pool) |
| Groupe Vigilance Sécurité | active | Free | owner + 1 |
| SafeGuard Consulting | suspended | Pro | owner + 1 |
| Prévention Alpha | active | Free | owner only — a freshly onboarded, near-empty tenant for empty-state screens |

None of the four get the rich Facility/Equipment/Intervention graph the main
organization does — that stays scoped to `ORGANIZATION_ID` on purpose, so the
other modules' integration tests keep their exact seeded counts. Each
secondary organization's owner is a dedicated user
(`UserFixtures::SECONDARY_ORG_OWNER_SEEDS`); the "extra" members reuse
`UserFixtures::bulkStaffId()` ids already seeded for the main organization,
demonstrating that one person can belong to more than one tenant.

## Notes

- Auth identities stay in the auth database (`users`, tokens, sessions, etc.).
- Organization RBAC is contextual and evaluated through `OrganizationAuthorizationPort`.
- **A suspended organization is read-only, enforced centrally.**
  `OrganizationAuthorizationService` refuses every permission that is not a
  read while `status` is `SUSPENDED`, before any grant is consulted. Three
  properties of that check are load-bearing:
  - It reads the **requested** permission, never the granted set. A member may
    hold `organization.*`; filtering the grants would strip their reads along
    with their writes.
  - `organization.settings.write` is the one write that survives. Without that
    escape hatch a suspended organization walls itself in: `RestoreOrganization`
    requires exactly this permission and there is **no platform-level bypass**.
  - An unreadable status is treated as operational, not as suspended. Failing
    closed would lock every member out of an organization that was never
    suspended, on nothing worse than a database blip.

  `organization.compliance.export` and `organization.assistant.use` are refused
  even though neither mutates: both spend resources for an organization whose
  access has been withdrawn.

  The classifier is `OrganizationPermissionCatalog::isRead()`, keyed on the
  `.read` suffix so a permission added later is classified without touching it.

- **`ARCHIVED` is read-only too, and reopening it is a platform action.**
  Before this rule, the archived guard existed on exactly **five** operations —
  suspend, update settings, remove logo, transfer ownership, reactivate member —
  all organization administration. The business surface (facilities, equipment,
  inspections, interventions, messaging, documents) was fully writable while
  archived. The central rule closes that.

  Reopening is enforced in `RestoreOrganizationProcessor`, not by withholding a
  permission: `ROLE_ADMIN` restores without an organization-scoped permission,
  and a caller who is not a platform administrator is refused once the
  organization is read as `ARCHIVED`. Suspension keeps its self-service path.

  **The archived rule lets two permissions through on purpose.**
  `organization.settings.write` and `organization.members.manage` gate exactly
  those five operations, which already answer **409 naming the archived state**.
  Denying them centrally would flatten five specific, documented answers into a
  bare 403. The authorization layer defers where a more precise answer already
  exists — that is what `OrganizationPermissionCatalog::isArchivalGuardedDownstream()`
  encodes, and it applies to `ARCHIVED` only, since suspension has no such
  handler guards and so shadows nothing.

  | Status | Reads | Business writes | Administration writes | Reopen |
  |---|---|---|---|---|
  | `ACTIVE` | yes | yes | yes | n/a |
  | `SUSPENDED` | yes | no | no, except `settings.write` | self-service |
  | `ARCHIVED` | yes | no | reach their handlers, which answer 409 | platform administrator only |
- **Bus failures are unwrapped, never caught bare (enforced).** Symfony
  Messenger's `HandleMessageMiddleware` wraps whatever a handler throws in
  `HandlerFailedException`, and `MessengerCommandBusAdapter::dispatch()` /
  `MessengerQueryBusAdapter::ask()` wrap THAT in
  `Shared\Application\Exception\MessengerRuntimeException`. A processor or
  provider that writes `catch (SomeDomainException)` straight around
  `dispatch()`/`ask()` therefore has a clause that never fires in production,
  and the intended 404/409 degrades to a 500. **Every processor and provider
  in this module catches `MessengerRuntimeException` and recovers the domain
  exception through the module trait
  `Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures`**
  (`findWrappedException()`, which walks both the `getPrevious()` chain and
  `HandlerFailedException::getWrappedExceptions()`). It is the module's single
  unwrapper — do not add a second one, and do not use
  `Shared\Application\Exception\MessengerExceptionUnwrapperTrait` here, whose
  `getPrevious()`-only walk misses a multi-handler failure. Two rules for the
  mapping order: keep domain-specific checks strictly BEFORE any bare
  `\InvalidArgumentException` check, or an internal library failure becomes a
  client 400; and keep the direct `catch` clauses in place wherever the
  processor still calls a guard port before dispatching — today that is
  `OrganizationPermissionGrantGuardPort` only, which runs in-process and does
  arrive bare, so both paths must stay covered there.
  `OrganizationLastAdminGuardPort` is **no longer** one of them: its census
  moved inside the handler transaction (see the last-administrator invariant
  below), so `OrganizationLastAdminException` always arrives wrapped and is
  mapped only through the `MessengerRuntimeException` clause.
  `tests/Functional/Api/OrganizationDomainFailureMappingApiTest.php`
  exercises this against a real bus and a real database and fails on a 500.
- **Every mutating operation on a plain-class resource sets `read: false`.**
  `OrganizationResource`, `OrganizationRoleResource`, `TeamResource` and their
  siblings are plain DTO classes, not Doctrine entities. API Platform's default
  pre-read step (`read: true`) sends `Patch`/`Put`/`Delete` through the generic
  `ReadProvider` first; with no provider on the operation it resolves nothing
  and throws `NotFoundHttpException` before the processor is ever called, so
  the endpoint answers 404 for every request regardless of input. Any
  operation carrying a `processor:` and no `provider:` must declare
  `read: false` — this bit `UPDATE_ORGANIZATION_ROLE` and `UPDATE_TEAM`, both
  now fixed and both covered by a functional test that asserts a 200 on the
  success path.
- `DELETE /organizations/{id}` is a reversible soft delete (archive): the record
  and all owned data (facilities, equipment, inspections, interventions, billing)
  are preserved; archived organizations are hidden from the default listing and
  restored through the settings PATCH (`isActive: true`).
- **Danger-zone slug confirmation (L3.2)**: `DELETE /organizations/{id}` requires
  a `slug` query parameter equal to the organization's CURRENT slug (normalized
  the same way `OrganizationSlug` itself normalizes — trim + lowercase —
  without constructing the value object, so arbitrary typed text is always
  treated as a mismatch, never as a VO validation error). The check runs in
  `DeleteOrganizationHandler`, after the not-found check and BEFORE the
  archive/idempotency branch, and on EVERY call, including a retry against an
  already-archived organization — a wrong or missing confirmation is always
  rejected (`OrganizationDeletionConfirmationMismatchException` →
  `DeleteOrganizationProcessor` → HTTP 422 Unprocessable Entity, mirroring how
  `UploadOrganizationLogoProcessor` maps input-validation-style failures, as
  opposed to the 409 Conflict used for persisted-state conflicts like a
  duplicate slug), never a silent no-op and never a 500. **This guard protects
  the ARCHIVE transition only — it is a "type the slug to confirm" UX
  safeguard against accidental clicks, not an authorization control and not a
  purge.** The organization and all of its data remain fully intact and
  restorable after a successful call; nobody should read "archived" here as
  "permanently removed" — actual permanent purge is the separate, deferred
  L3.3 lot (retention-window hard delete), not built by this guard. Because
  this is a UX safeguard rather than a security boundary, a mismatch is
  **not** written to the audit ledger (unlike the RBAC guards below, whose
  refusals ARE audited).
- **Ownership transfer (P2.1) and self-removal / leave (P2.2)**:
  `POST /organizations/{id}/transfer-ownership` (`TransferOrganizationOwnershipHandler`)
  and `DELETE /organizations/{organizationId}/members/me`
  (`LeaveOrganizationHandler`) are Presentation-only additions over an
  already-landed Application slice; the handlers own every decision, the
  processors only translate. **Existence/slug oracle closed**: the handler
  resolves the acting user's OWN membership first — before the slug
  confirmation is even looked at, and before the owner check.
  A caller who is not an active member gets `OrganizationMemberNotFoundException`
  (→ 404), byte-for-byte the same problem-details envelope (`@context`,
  `@id`, `@type`, `title`, `status`, `type`) a nonexistent organization id
  produces, so a stranger can neither confirm the organization exists nor
  use the slug-mismatch response to validate guesses against it (see
  `testTransferOwnershipRejectsNonMemberOfExistingOrganizationLikeANonexistentOne`
  /`testTransferOwnershipRejectsNonexistentOrganizationWithTheSameShapeAsANonMember`
  in `OrganizationApiTest`). An active member who is simply not the owner
  legitimately already knows the organization exists, so THAT check —
  and only then the slug confirmation — runs next: the current-owner check
  (`OrganizationAccessDeniedException::ownershipTransferRequiresCurrentOwner()`
  → 403) is intentionally independent of RBAC — no `organization.*`
  permission substitutes for owning the organization — so
  `TransferOrganizationOwnershipProcessor` does NOT call
  `OrganizationAuthorizationPort::hasPermission()`, unlike every other
  mutating organization endpoint. Transfer's danger-zone slug guard mirrors
  DELETE's byte-for-byte (`OrganizationDeletionConfirmationMismatchException`
  → 422), but carries `slug` in the request body instead of a query
  parameter, since the request already has a JSON body for
  `newOwnerUserId`. `OrganizationArchivedException` and
  `OrganizationOwnershipUnchangedException` both map to 409 Conflict,
  matching how `OrganizationArchivedException` is already mapped on
  `PATCH /organizations/{id}` (persisted-state conflict, not a
  confirmation failure). `OrganizationMemberNotFoundException` (transfer
  target not an active member) also maps to 404, matching
  `AssignOrganizationRoleToMemberProcessor`'s convention for the same
  exception. **Admin-role grant is guard-checked and best-effort**: the new
  owner is granted the system `admin` role, mirroring the owner-gets-admin
  invariant `CreateOrganizationHandler` applies at creation, but ONLY after
  `OrganizationPermissionGrantGuardPort::assertCanAssignRoles()` confirms
  the acting (previous) owner still holds every permission that role
  carries — the same no-privilege-escalation check
  `AssignOrganizationRoleToMemberProcessor`/`AddOrganizationMemberProcessor`
  already enforce, applied here from inside the handler rather than a
  processor since the transfer must already have committed before this
  step runs. The entire grant step (role lookup, guard, `assignRole`,
  `OrganizationRoleAssignedEvent`) is wrapped in a single try/catch
  `Throwable`: a missing `admin` system role, a guard refusal, or any
  unexpected persistence failure is logged via `LoggerPort` and skipped —
  never surfaced to the caller, since the ownership transfer itself was
  already durably saved and its event dispatched before this step even
  starts. **Output DTO choice**: `TransferOrganizationOwnershipResult`
  carries only `organizationId`/`previousOwnerUserId`/`newOwnerUserId`/
  `transferredAt` — not the full organization — so
  `TransferOrganizationOwnershipProcessor` re-reads via `GetOrganizationQuery`
  and returns `OrganizationOutput`, the same `buildOutput()` pattern
  `UpdateOrganizationSettingsProcessor` and `ChangeOrganizationPlanProcessor`
  already use for every other mutating organization operation, rather than
  inventing a dedicated lean Output type. The operation declares
  `status: 200` (not API Platform's POST default of 201): the call mutates
  and returns an existing resource, it does not create one, matching the
  200 every other mutating organization endpoint (`PATCH`) returns. Leave
  reuses the SAME last-administrator guard `RemoveOrganizationMember`
  already depends on (`OrganizationLastAdminGuardPort::assertCanRemoveMember`,
  called from inside the handler transaction on both paths — see the
  last-administrator invariant below) and additionally refuses the
  organization's current owner
  (`OrganizationOwnerCannotLeaveException::mustTransferOwnershipFirst()`),
  checked BEFORE the last-admin guard so an owner who is also the sole
  administrator gets the more actionable "transfer ownership first" message.
  Both exceptions map to 409. **Route disambiguation**: `DELETE
  /members/me` and `DELETE /members/{memberId}` share a path shape at the
  same segment position; `REMOVE_ORGANIZATION_MEMBER`'s operation now
  carries an explicit UUID `requirements: ['memberId' => '...']` (the same
  pattern `AuditEventResource::GET` already uses for `{id}`) so `{memberId}`
  can never match the literal string `me`, on top of `LEAVE_ORGANIZATION`
  being declared first in `OrganizationMemberResource`'s operations array.
  **Both processors dispatch through `CommandBusPort` and catch
  `Shared\Application\Exception\MessengerRuntimeException`, unwrapping it
  through the `UnwrapsOrganizationBusFailures` trait** — see the
  module-wide bus-unwrapping rule below, which every processor and provider
  in this module now follows.
- Plan quotas are enforced INSIDE the create/invite/add handlers, in the same
  transaction as the insert, serialized per (organization, resource) by a
  Postgres transaction-scoped advisory lock (`OrganizationQuotaLockPort`) so
  concurrent creates at the cap cannot both pass. `PostgresOrganizationQuotaLockAdapter`
  acquires **unconditionally** — every environment this runs in is Postgres, and
  a misconfigured connection must fail loudly rather than silently drop the
  serialization that guards the invariant. It is not a no-op anywhere.
  `AddOrganizationMemberCommand::$enforceQuota` is false only on the
  invitation-accept path, which counts active members only
  (`assertCanAcceptMember`) so the accepted invitation never blocks itself.
  **`ReactivateOrganizationMember` (P2.3) is gated by the SAME member cap**:
  reactivating a removed member brings the organization's active-member count
  back up exactly like the re-add branch of `AddOrganizationMemberHandler`
  does, so `ReactivateOrganizationMemberHandler` now also injects
  `OrganizationQuotaPort`/`TransactionManagerPort` and calls
  `assertCanAdd(…, OrganizationQuotaResource::MEMBERS)` inside the same
  transaction that flips `isActive` and saves — mirroring the re-add path
  rather than leaving reactivation as a quota-free backdoor around the cap
  a direct `POST /members` re-add would have hit. `OrganizationQuotaExceededException`
  maps to 409, same as everywhere else this exception surfaces. Covered by a
  dedicated handler unit test (`ReactivateOrganizationMemberHandlerTest::testInvokeThrowsQuotaExceededAndLeavesTheMemberInactiveWhenTheMemberCapIsReached`);
  not repeated as a functional test since the quota-summary/plan-catalog
  scaffolding it would need is already exercised elsewhere.
- **`OrganizationQuotaPort::assertCanAddMultiple(organizationId, resource, count)`**
  is the batched sibling of `assertCanAdd()`, added for callers that create
  several resources of the same kind atomically (Facility's
  `DuplicateFacilitySubtreeHandler` duplicating a whole subtree in one
  transaction — see `src/Facility/MODULE.md`). Same advisory lock, same
  transaction requirement; `count <= 0` is a no-op. Implemented by
  `OrganizationQuotaService` alongside `assertCanAdd()`.
- **The whole `OrganizationQuotaPort` surface speaks
  `Application/Contract/Quota` types** (2026-08-18 quota-contract migration):
  every method takes the contract enum
  `Organization\Application\Contract\Quota\OrganizationQuotaResource` and
  every guard throws
  `Organization\Application\Contract\Quota\OrganizationQuotaExceededException`
  (mapped to 409 in `config/packages/api_platform.yaml`), because the port's
  callers are sibling modules — Facility, Equipment, Inspection — which may
  only import `Application\Contract` types (`CrossModuleDomainBoundaryTest`).
  `OrganizationQuotaService` maps each contract case to the Domain enum
  `Organization\Domain\ValueObject\OrganizationQuotaResource` internally
  (plan `limitFor()`, usage statistics, advisory lock). The former Domain
  exception `Organization\Domain\Exception\OrganizationQuotaExceededException`
  was deleted with the migration — nothing threw it anymore.
- **`OrganizationQuotaPort::assertProjectedCanAdd()`** is a **projection**
  sibling to `assertCanAdd()`, added for bulk CSV import v2's dry-run mode
  (`src/Import/MODULE.md`): `getLimit()`/`getUsage()` plus a caller-supplied
  `$additionalOffset` (resources already provisionally counted elsewhere in
  the same batch), with **no** advisory lock and **no** transaction
  requirement — a caller that persists nothing has no insert to serialize
  against. `CreateFacilityHandler`/`CreateEquipmentHandler` call it from
  their `dryRun` branch instead of `assertCanAdd()`. It answers "would this
  exceed the cap right now", never a TOCTOU-safe guarantee — never use it on
  the write path.
- **P2.3 member HTTP slice** (`GetOrganizationMember` provider,
  `ReactivateOrganizationMember`/`SetOrganizationMemberRoles` processors):
  all three dispatch through `CommandBusPort`/`QueryBusPort` and unwrap
  `Shared\Application\Exception\MessengerRuntimeException` via the shared
  `UnwrapsOrganizationBusFailures` trait (`GetOrganizationNavigationCountersProvider`'s
  pattern) rather than catching the bare domain exception directly off
  `dispatch()`/`ask()` — the module-wide rule stated below. `GetOrganizationMember`
  resolves by member id alone (it is also used cross-module by Intervention,
  so its signature was not changed); the provider adds the organization-scope
  check itself — a member that exists but belongs to a different organization
  than the URL reads as 404, never a cross-tenant leak. `SetOrganizationMemberRoles`
  replaces `SetOrganizationMemberRolesResult`'s two missing fields (`isActive`,
  `joinedAt`) so its `OrganizationMemberOutput` is complete like every sibling
  mutating-member endpoint's. `ListOrganizationMembersProvider` was rewritten
  in the same change: it used to run `CollectionSearcher`/`CollectionSorter`/
  `array_slice` over the FULL member list even after `ListOrganizationMembersHandler`
  started honoring pagination — meaning every page after the first silently
  returned page 1's rows again, and `totalItems` reflected the unfiltered
  count. The provider now only extracts `search`/`status`/`roleId`/`order`/
  `page`/`itemsPerPage` from the request and forwards them straight to
  `ListOrganizationMembersQuery`, trusting the handler/repository the way
  `ListUserOrganizationsProvider` already does; an invalid `status` value is
  rejected with 422 before the query is even dispatched.
- **P2.4 role HTTP slice** (role rename, `GetOrganizationRole` provider, real
  role-list pagination): `UpdateOrganizationRoleCommand` gained an optional
  `?name`; renaming a role reuses `CreateOrganizationRoleHandler`'s exact
  uniqueness check (`OrganizationRoleRepositoryPort::findByOrganizationAndName`)
  and the same `InvalidArgumentException('Role name already exists for this
  organization.')`, and `UpdateOrganizationRoleHandler` refuses ANY change
  (permissions, description, or name) to a system role with
  `InvalidArgumentException('System roles cannot be modified.')` — both map to
  HTTP 400 via `UpdateOrganizationRoleProcessor`'s existing generic
  `InvalidArgumentException` catch, unchanged by this slice, mirroring how
  `CreateOrganizationRoleProcessor` maps its own duplicate-name refusal.
  **Asymmetry closed**: `PATCH .../roles/{roleId}` used to report a role from
  another organization as `InvalidArgumentException('Role not found in this
  organization.')` → 400, while `GET`, `DELETE` and both member-role
  operations answered 404 for the identical case. It now throws
  `OrganizationRoleNotFoundException` → 404 like its siblings — the message
  had said "not found" all along, only the exception type disagreed
  (`testUpdateRoleRejectsRoleInAnotherOrganization` in
  `OrganizationRoleApiTest` pins it). `GetOrganizationRoleProvider` mirrors
  `GetOrganizationMemberProvider`'s `UnwrapsOrganizationBusFailures`
  pattern for both `OrganizationNotFoundException` and
  `OrganizationRoleNotFoundException`. **`ListOrganizationRolesProvider`
  landmine fixed**: it used to fetch every role unpaginated and return a bare
  array — API Platform then normalized it as a single, unpaginated page, so
  `page`/`itemsPerPage` were silently ignored. `ListOrganizationRolesQuery`
  gained an optional `?Pagination` (used by `ListOrganizationMembersProvider`'s
  role-name lookup only when left `null`, which it still is — see the
  query's own docblock), but this provider deliberately does NOT pass it:
  unlike members, there is no search/sort push-down for roles at the
  repository level, so pushing DB-side pagination before the provider's own
  `CollectionSearcher`/`CollectionSorter` run would paginate the WRONG
  (unfiltered, unsorted) set. The provider therefore still asks for the full,
  unpaginated list, filters/sorts in memory exactly as before, and NOW also
  slices the result for the requested page — `totalItems` is the count AFTER
  search filtering, never the organization's raw role count. A role list is
  small per organization, so the in-memory approach is a deliberate,
  bounded choice, not a scalability compromise.
- **P2.5**: `SuspendOrganization`/`RestoreOrganization` are dedicated
  lifecycle endpoints layered over an already-landed Application slice — the
  handlers are idempotent (`SuspendOrganizationHandler`/
  `RestoreOrganizationHandler` no-op and skip the event dispatch when the
  organization is already in the target state), so a second call still
  returns HTTP 200 with the refreshed `OrganizationOutput`, never a 409.
  **Both coexist with, and do not replace, the legacy `isActive` toggle** on
  `PATCH /organizations/{id}` — that remains the third way to reach the same
  two transitions. **Permission choice**: both require
  `organization.settings.write`, deliberately the SAME permission the legacy
  toggle already requires, rather than the stricter `organization.delete`
  `DELETE /organizations/{id}` (archive) uses. Gating the dedicated endpoint
  stricter than the legacy path that reaches the identical state transition
  would be security theater — a caller blocked on `/suspend` could simply
  PATCH `isActive: false` instead — so the two must agree; unlike archive,
  suspend/restore neither hide data from the default listing nor require the
  danger-zone slug confirmation, so `organization.delete`'s extra severity
  was not warranted here. `RemoveOrganizationLogoCommand` is exposed as
  `DELETE /organizations/{organizationId}/logo`, mirroring
  `UploadOrganizationLogoProcessor`'s `organization.settings.write` gate;
  `RemoveOrganizationLogoHandler` is idempotent when the organization already
  has no logo (204, storage untouched, no event). All three processors
  follow the module-wide bus-unwrapping rule below (catch
  `MessengerRuntimeException`, recover the domain exception through
  `UnwrapsOrganizationBusFailures`). **Coarse-permission collapse**: because `hasPermission()` runs
  BEFORE the command dispatch on all three endpoints, a caller with no
  membership row in a given organization id always resolves zero
  permissions — so a nonexistent organization and an existing one the caller
  simply isn't part of both surface as the same 403, and
  `OrganizationNotFoundException`'s 404 mapping in each processor is
  effectively unreachable through HTTP for that caller shape (documented via
  `testSuspendOrganizationRejectsNonexistentOrganization` and its restore/
  logo-delete counterparts in `OrganizationApiTest`, rather than silently
  omitted).
- Role/permission writes are protected pre-dispatch by
  `OrganizationPermissionGrantGuardPort` (no privilege escalation) — a pure read
  of the caller's own permissions, so it needs no serialization and stays in the
  processor. The last-administrator guard does NOT: see the invariant below, it
  runs inside the handler transaction.
- **An organization always keeps at least one active administrator**
  (`OrganizationLastAdminGuardPort`, HTTP 409). An administrator is an active
  member whose effective permissions grant `organization.members.manage`
  (directly or through a wildcard) — the capability needed to re-admit members
  and recover the organization. Lose the last one and the organization is
  unrecoverable without operator intervention.

  The assertion is a **check-then-write**, so it is only an invariant when the
  census and the write it authorizes are serialized. Every authoritative call
  therefore runs INSIDE the handler's `main` transaction and takes the
  per-organization MEMBERS advisory lock (`OrganizationQuotaLockPort`) before
  reading. The lock is transaction-scoped: run pre-dispatch from a processor, it
  is released before the write commits, and two concurrent removals each read
  "another administrator remains", both commit, and the organization is stranded.

  Six call sites, each guarded inside its own transaction:
  `RemoveOrganizationMemberHandler`, `RemoveOrganizationRoleFromMemberHandler`,
  `UpdateOrganizationRoleHandler`, `DeleteOrganizationRoleHandler`,
  `LeaveOrganizationHandler`, and `SetOrganizationMemberRolesHandler` (whose
  per-revoked-role guard loop sits inside its existing `transactional()`
  closure). All six are wired with
  `$transactionManager: '@organization.main_transaction_manager'` in
  `config/modules/organization.yaml`.

  Because the refusal is now raised by a handler, it reaches the processor
  wrapped by the command bus and is recovered through the module's single
  unwrapper, `UnwrapsOrganizationBusFailures` — not a hand-rolled helper and not
  `Shared\Application\Exception\MessengerExceptionUnwrapperTrait`.

  The one exception is `assertCanRemoveMembers()`, the batch endpoint's
  pre-check: it is **deliberately unlocked and advisory**, rejecting a doomed
  batch early with a message naming the real reason instead of making the caller
  watch every id fail. It is not the authority — a batch that passes it can still
  be refused mid-flight, and `RemoveOrganizationMembersProcessor` maps that
  wrapped refusal to a 409 rather than tallying it into `failedIds`, which would
  report a partial success and hide the lockout.

  Proven by
  `tests/Integration/Organization/Application/Service/OrganizationLastAdminGuardConcurrencyIntegrationTest.php`,
  which drives two real overlapping transactions on two raw connections and
  asserts the loser blocks on the lock (SQLSTATE `55P03` under a short
  `lock_timeout`) and is refused once it reads the committed census.
- Plan changes are usage-aware: when the target plan's caps sit below the
  organization's CURRENT usage, an unacknowledged self-service change is
  refused (HTTP 409 listing the exceeded resources, e.g. `members 12/10`);
  re-submitting with `acknowledgeOveruse: true` applies it. The Stripe
  webhook path (`OrganizationPlanAssignmentAdapter`) always acknowledges —
  billing is the source of truth — and the handler then notifies the
  organization owner (`organization.plan_over_quota` notification) and
  records the overage in the `organization.plan_changed` audit event
  (`over_quota_resources`). Grace path: nothing is deleted; the existing
  create guards keep refusing NEW creations until usage fits the plan. No
  persisted "over quota" flag: the state is derivable at any time from
  usage vs caps (settings Usage tab), so a stored flag could only drift.
- **Plan presentation catalog (L3.8)**: `PlanOutput`/`GetPlanResult` add
  `tagline` (short marketing line) and `perks` (bullet list, in display
  order) so the frontend's plan-comparison card and "current plan" card
  render without hardcoding copy. Both are resolved in
  `GetPlanResult::fromDomain()` from `Domain/Catalog/PlanPresentationCatalog`
  — a `*Defaults`-style catalog (mirrors `OrganizationApprovalDefaults`) that
  holds translation message identifiers, not the copy itself: the actual
  strings live in `translations/plans.{en,fr,es}.yaml` (domain `plans`,
  resolved through the shared `TranslationPort`) so copy can be edited
  without a code change, the same seam already used for transactional
  emails (`translations/emails.*.yaml`). A plan key absent from the catalog
  (e.g. a custom plan created by a platform administrator) simply gets
  `tagline: null` / `perks: []` — never an error. **`tagline`/`perks` are
  MARKETING copy only and must NEVER be read to decide what a plan grants**:
  entitlement is exclusively `Plan::limitFor()` / `OrganizationQuotaCatalog`
  (quotas) and, for the PDF document exports (Compliance safety register,
  Inspection report + non-conformities report, Equipment sheet), the
  `pro`/`max` allow-list in `OrganizationExportEntitlementAdapter` — one
  adapter implementing `ComplianceExportEntitlementPort`,
  `InspectionReportEntitlementPort` and `EquipmentReportEntitlementPort`
  (see `src/Compliance/MODULE.md`).
  A plan key present in `PlanPresentationCatalog` with no matching
  entitlement rule elsewhere (or vice versa) is expected and intentional —
  the two catalogs are never meant to stay in lockstep. The Billing module's
  `SubscriptionOutput` separately adds `planName`/`currency`/`monthlyAmount`/
  `yearlyAmount` (see `src/Billing/MODULE.md`) so a client can render the
  "current plan" card from the subscription call alone, without a second
  round-trip to `/plans`; that enrichment does not touch this catalog and
  carries no perks (pricing/name only).
- Every regulated organization action emits a domain event
  (`src/Organization/Domain/Event/`) recorded into the tamper-evident audit
  ledger by Audit's `AuditEventSubscriber`: lifecycle (created / archived /
  restored / suspended), roles (created / updated / deleted / assigned /
  unassigned), members (added / removed — the bulk processor loops the single
  command, so each removal is audited), invitations (sent incl. resend /
  accepted / revoked), plan changes, plus the refused security attempts
  (`permission_grant_denied`, `last_admin_lockout_prevented`). Handlers
  dispatch AFTER durable persistence (never inside a transactional closure);
  the two guard services dispatch immediately before their refusal throw.
- Dashboard `trends`: one running-total sparkline per KPI (`facilities`,
  `members`, `equipment`, `inspections`), one point per day bucket across
  `period.from`..`period.to`. Each series is anchored on the CURRENT KPI
  total (`overview.<kpi>.total`/`memberCount`) and walked backward using the
  corresponding by-day count port (`FacilityStatisticsPort::countFacilitiesCreatedByDay`,
  `OrganizationMemberRepositoryPort::countJoinedByDay`,
  `EquipmentStatisticsPort::countEquipmentCreatedByDay`,
  `InspectionStatisticsPort::countInspectionsPerformedByDay`), so it is exact
  for the default "ends near now" window and an approximation for explicitly
  historical windows.
- **Chart-level dashboard trends (`/dashboard/trends/*`) — two-series combining
  (L3.9)**: each `/dashboard/trends/{metric}` endpoint returns one primary
  `series`, unchanged and byte-for-byte compatible with existing consumers.
  The two non-conformity endpoints (`non-conformities-opened` /
  `non-conformities-resolved`) additionally accept an optional `metrics`
  query filter — a comma-separated list of the OTHER combinable metric
  identifiers (today only `non_conformities_opened`/`non_conformities_resolved`
  combine with each other, declared in
  `GetOrganizationDashboardTrendProvider::METRIC_COMBINABLE_METRICS`) — so a
  chart plotting "opened vs resolved" can render from a single call instead
  of zipping two independently-bucketed responses (which could silently
  desync if the two calls ever resolved a different period/timezone/
  granularity). Every requested metric shares the SAME resolved period,
  timezone and granularity as the primary metric
  (`GetOrganizationDashboardTrendHandler::buildSeriesByMetric`, built with
  `Application/Support/DashboardSeriesBuilder`, never a local reimplementation
  of the bucketing helpers) and is permission-checked individually — both the
  provider and the handler loop
  `OrganizationPermissionCatalog::dashboardTrendReadDependencies()` over
  every requested metric (primary + additional), so requesting two metrics
  through `metrics` can never let a caller read one it lacks rights to by
  hiding it behind a metric it is allowed to see. Results land in the
  response's `seriesByMetric` map (keyed by metric identifier, includes the
  primary metric too), populated only when more than one metric was
  requested; `metrics` on a non-combinable trend endpoint, or an
  unrecognized/duplicate metric value, is rejected with HTTP 400. At most
  `GetOrganizationDashboardTrendHandler::MAX_REQUESTED_METRICS` (4) distinct
  metrics may be requested per call, bounding the statistics-port fan-out.
- **Dashboard non-conformity severity breakdown (L3.10)**: `overview.nonConformities`
  additionally exposes `severityLow`/`severityMedium`/`severityHigh`/`severityCritical`
  — an org-wide, ALWAYS-unfiltered current-snapshot count of every
  non-conformity by severity, regardless of status
  (`NonConformityStatisticsPort::countNonConformitiesBySeverity`, backed by
  `Inspection\Infrastructure\Adapter\Organization\NonConformityStatisticsAdapter`
  → `NonConformityRepository::countBySeverityForOrganizationId`). This is
  unlike the neighboring `open`/`inProgress`/`done`/`waived`/`overdue`/
  `criticalOpen` fields, which DO honor the `nonConformityStatus`/
  `nonConformitySeverity` query filters. Reuses the existing port method — no
  new port was added and no second query round-trip beyond the one extra
  grouped-count call the breakdown needs. The backend's four real severity
  levels (`low`/`medium`/`high`/`critical`) are authoritative in the response
  contract; there is no three-level (Critical/Major/Minor) relabeling. No
  cache-key discriminator is needed for this section (unlike
  `recentInterventions` below): the severity breakdown is sourced from the
  same non-conformity data already gated behind `organization.inspection.read`,
  which is a hard, non-optional entry in
  `OrganizationPermissionCatalog::dashboardReadDependencies()` and is asserted
  (both in the provider and at the top of the handler) before the cache is
  even read — every caller who can reach the cache read already holds it, so
  no permission-flag discriminator can ever differ between two callers
  sharing a cache key.
- Dashboard `recentInterventions`: the 5 most recently updated field
  interventions (any status), resolved through the new
  `InterventionStatisticsPort` (aliased to
  `Intervention\Infrastructure\Adapter\Organization\InterventionStatisticsAdapter`,
  the first Organization-facing adapter in the Intervention module — mirrors
  `Facility\...\Organization\FacilityStatisticsAdapter`). Gated by
  `organization.interventions.read`, checked directly via
  `OrganizationAuthorizationPort::hasPermission` (deliberately **not** added
  to `OrganizationPermissionCatalog::dashboardReadDependencies()`, so a
  member without field-intervention access still gets the rest of the
  dashboard, just with an empty list instead of a 403). The handler resolves
  the assigned facility's name (`FacilityStatisticsPort::getFacilityNamesByIds`)
  and the responsible member's user id
  (`OrganizationMemberRepositoryPort::findUserIdsByMemberIds`); the
  Presentation provider alone resolves the display name/avatar via
  `GetUserQuery` (same batch-dedupe pattern and fallback chain as
  `ListOrganizationMembersProvider`). Because this section is
  permission-dependent, the dashboard cache key includes an
  `includeInterventions` discriminator so a cached payload never leaks the
  list across differently-permissioned users.
- Settings blob: `OrganizationSettings` now carries six sections
  (`notifications`, `regional`, `compliance`, `automation`, `approval`,
  `assistant`) plus
  a root `version` field (`SCHEMA_VERSION`, written on save, ignored on read —
  reserved as an upcasting anchor; additions never need it because
  `fromArray` applies defaults). `compliance` stores ONLY the organization's
  customizations; effective values are resolved on read against
  `Domain/Catalog/OrganizationComplianceDefaults.php` (severity SLAs
  low:90/medium:30/high:7/critical:1 days, per-equipment-type inspection
  periodicities, 30-day reminder window), so catalog updates propagate to
  non-customized organizations automatically. `automation` toggles default
  to OFF (automations are always opt-in). `approval` (R17) stores ONLY the
  organization's customized per-action-type rules; every action type
  defaults to `enabled: false` (`Domain/Catalog/OrganizationApprovalDefaults.php`
  — default `minApproverRole: admin`, `allowSelfApproval: false`,
  `approvalTtlDays: 14`, bounds 1–90) — see `src/Approval/MODULE.md` for the
  gate/executor mechanics. `assistant` gates the AI assistant and defaults to
  `enabled: false` (`Domain/Catalog/OrganizationAssistantDefaults.php` —
  `model: null` meaning "use the operator-configured default",
  `temperature: 0.2` with bounds 0.0–2.0, `includeBusinessContext: true`).
  Enabling it sends conversation content to the inference backend, so it is
  opt-in twice over: the organization must flip this flag AND grant
  `organization.assistant.use`, which is deliberately not part of the `member`
  system role. **No field here can influence the inference backend URL** —
  that is operator configuration (`OLLAMA_BASE_URL`), so a tenant setting can
  never redirect inference traffic. `model` is shape-validated here and
  allowlist-validated against `OLLAMA_ALLOWED_MODELS` by the Assistant module;
  omitting `model` in a PATCH leaves the override unchanged, while sending an
  empty string reverts it to the operator default.
- **Legal profile (L3.1)**: `country` (ISO 3166-1 alpha-2, validated against a
  hardcoded officially-assigned code list — `Domain/ValueObject/OrganizationCountry`,
  deliberately not `symfony/intl` so the Domain layer stays framework-free),
  `legalType` (`Domain/ValueObject/OrganizationLegalType`, a country-agnostic
  enum: sole proprietorship, partnership, limited liability company, public
  limited company, non-profit association, public entity, other — see
  `GET /organizations/legal-types` for the value/label catalog, a dedicated
  reference-catalog resource), `legalName` (free
  text, may differ from the display `name`), `registrationNumber` and
  `vatNumber` (the two previously-orphan value objects
  `Domain/ValueObject/OrganizationRegistrationNumber` /
  `OrganizationVatNumber`, length + `/^[A-Z0-9\-\/. ]+$/` validated). Unlike
  the settings sections above, these are plain nullable columns on
  `OrganizationRecord` (not part of the `settings` JSON blob) mapped directly
  on the `Organization` aggregate, mirroring how `description`/`logoUrl` are
  handled. The whole profile is optional — an organization with none of it
  set is valid — and the "complete/incomplete" badge is derived by the
  frontend from field presence, never persisted. Each of the five PATCH
  fields is independently optional: omitting a field (or sending `null`)
  leaves it unchanged, and sending an empty string clears it — the same
  convention already used by `description`. Writes are gated by
  `organization.settings.write`, the same permission as the rest of
  organization settings; a change to any of the five fields records a single
  `legal` entry in `OrganizationSettingsUpdatedEvent.changedFields` (not five
  granular entries, mirroring how `notifications`/`regional` record one entry
  per section).
- `EquipmentTypeCatalogPort` (outbound, aliased to
  `Equipment\Infrastructure\Adapter\Organization\EquipmentTypeCatalogAdapter`)
  exposes equipment-type values/labels so the settings API can validate
  periodicity keys without the Organization module ever referencing the
  Equipment domain enum. `ApprovalActionTypeCatalogPort` (outbound, aliased
  to `Approval\Infrastructure\Adapter\Organization\ApprovalActionTypeCatalogAdapter`)
  does the same for `approval.actionRules` keys against the Approval
  module's action-type catalog, consumed by the class-level
  `ValidApprovalPolicy` constraint on `UpdateOrganizationApprovalInput`.
  `ApprovalPolicyPort` / `ApprovalMemberDirectoryPort` (inbound-to-Approval,
  hosted here as `Infrastructure/Adapter/Approval/OrganizationApprovalPolicyAdapter`
  / `OrganizationApprovalMemberDirectoryAdapter`) resolve the effective
  approval policy and member-role satisfaction for the Approval module's
  gate and decision handlers — the `admin` tier is detected via the
  `organization.*` wildcard permission rather than a separate role read
  model.
- **L2.2 (Assistant business-context seam)**: `AssistantOrganizationSettingsPort`
  (inbound-to-Assistant, hosted here as
  `Infrastructure/Adapter/Assistant/OrganizationAssistantSettingsAdapter`,
  mirrors `OrganizationApprovalPolicyAdapter`) exposes
  `isEnabledFor()`/`includeBusinessContextFor()` reading the organization's
  own `OrganizationAssistantSettings` value object. Both are consumed:
  `isEnabledFor()` by `Assistant\Application\Service\AssistantAccessPolicy`,
  which every assistant endpoint passes through, and
  `includeBusinessContextFor()` by `GenerateAssistantReplyHandler`, gating
  `AssistantPromptBuilder`'s business-context injection.
  Fails closed (both methods return `false`) on an unknown/malformed
  organization — so a lookup failure disables the assistant rather than
  leaking business context.
- **Member `isOwner` / role `memberCount`**: `ListOrganizationMembersHandler`
  resolves the organization's `ownerUserId` ONCE (the same `findById` read
  already used for the not-found check) and compares it against each
  member's `userId`, so `OrganizationMemberOutput::$isOwner` never costs a
  per-row lookup. `ListOrganizationRolesHandler` and
  `GetCurrentOrganizationMemberProfileHandler` (roles tab and the `/me`
  endpoint) resolve `OrganizationRoleOutput::$memberCount` /
  `CurrentOrganizationMemberProfileOutput::$roles[].memberCount` from
  `OrganizationMemberRepositoryPort::countActiveMembersGroupedByRoleId`, a
  single `GROUP BY role_id` query over `organization_member_roles` joined to
  `organization_members` and filtered on `is_active = true` (removal is a
  soft `OrganizationMember::deactivate()`, so this join is required to keep
  deactivated members out of the count) — one query per request regardless
  of how many roles the organization has; roles absent from the map default
  to `memberCount: 0`.

- **Caller membership projection (`isOwner`/`roles`), shared by the list, the
  single GET, and every mutation output**: `GET /api/organizations` items,
  `GET /api/organizations/{id}`, and the refreshed `OrganizationOutput`
  returned by suspend/restore/transfer-ownership/settings-update all expose
  `isOwner` and `roles` for the REQUESTING user (Account → Roles
  "Organization memberships" section; the org switcher ignores them).
  A single Inbound port, `OrganizationCallerMembershipPort` (implemented by
  `Organization\Application\Service\OrganizationCallerMembershipService`),
  is the ONE place the projection is computed, so the four consumers can
  never disagree:
  - `isOwner(organizationOwnerUserId, callerUserId)` — a plain equality
    check against the `Organization` aggregate's own `ownerUserId`, never
    membership-dependent (the owner stays the owner with an inactive or
    missing membership row).
  - `findActiveCallerMembership(organizationId, callerUserId)` — one
    indexed `OrganizationMemberRepositoryPort::findByOrganizationAndUser`
    lookup, filtered to ACTIVE (mirrors `ListUserOrganizationsHandler`'s own
    active filter so the two can never disagree on who counts as a member).
  - `resolveRoles(organizationId, ?membership)` — the
    `findRoleIdsForMember` → `findByIdsInOrganization` join (same shape as
    `ListOrganizationMembers` / `GetCurrentOrganizationMemberProfile`),
    returning `[]` for a null membership or one with no assigned role.

  `ListUserOrganizationsHandler` already has the caller's membership
  bulk-loaded (one `findByUserId` call for the whole page), so it calls
  `resolveRoles()` directly with that membership — no per-row query.
  `GetOrganizationHandler` does not have it pre-loaded, so
  `GetOrganizationQuery` grew an optional `?string $callerUserId`: when
  provided, the handler pays one extra `findActiveCallerMembership` lookup
  plus, only when the caller holds roles, the role-name join — at most 2
  extra indexed queries beyond the existing `findById`/plan read. `null` (no
  `callerUserId`) preserves the original behavior of leaving `isOwner`/
  `roles` `null` — still true for the two callers of `GetOrganizationQuery`
  that do not resolve caller membership (`ChangeOrganizationPlanProcessor`,
  `UploadOrganizationLogoProcessor`).

  Every other consumer of `GetOrganizationQuery` that DOES need the
  projection (`GetOrganizationProvider` and the suspend/restore/
  transfer-ownership/settings-update processors, all of which re-read
  through this same query to build their response) now passes
  `callerUserId: $user->getId()`. **Transfer ownership is the interesting
  case**: `$callerUserId` is deliberately the ACTING user — the PREVIOUS
  owner — and `GetOrganizationHandler` resolves `isOwner` against the
  organization's (already updated) `ownerUserId`, so the response correctly
  reports `isOwner: false` for the caller after a successful transfer,
  without any special-casing in the processor.

  The five HTTP consumers share the actual `GetOrganizationResult` →
  `OrganizationOutput` field-by-field mapping too, through
  `Organization\Presentation\Api\Trait\OrganizationOutputMapperTrait`
  (mirrors `InvitationOutputMapperTrait`'s existing shape), so a change to
  one no longer risks drifting from the other four.

  The fields ride on the shared `GetOrganizationResult`/`OrganizationOutput`
  as NULLABLE members (`isOwner: ?bool`, `roles: ?list<{id, label}>`, where
  `label` is the role name) — `null` means "caller membership not resolved
  by this operation" (the two exceptions above), a concrete `bool`/list
  everywhere else, including a member with no assigned role (`roles: []`,
  never an error, no role query issued). Read-only aggregation: no schema
  change, no migration.

- **Sidebar navigation counters (L3.11)**: `GET /organizations/{organizationId}/navigation-counters`
  answers the "does this org have work waiting" badge question for the
  frontend sidebar without paying for the full `/dashboard` payload (KPIs,
  trends, comparisons). Access mirrors `GET /organizations/{id}/me`
  (`GetCurrentOrganizationMemberProfileHandler`): the caller only needs an
  ACTIVE membership, checked directly in `GetNavigationCountersHandler`
  (`OrganizationMemberRepositoryPort::findByOrganizationAndUser` +
  `isActive()`), not a specific permission — every member should see the
  sidebar badges. Each of the three counters is then individually soft-gated
  on the same permission its data already requires elsewhere
  (`organization.interventions.read` / `organization.inspection.read` /
  `organization.interventions.review`,
  checked via `OrganizationAuthorizationPort::hasPermission`), degrading to
  `0` instead of a 403 for a member without that permission — the same
  pattern `GetOrganizationDashboardHandler` already uses for
  `recentInterventions`/`overview.interventions`. `openInterventions` reuses
  `InterventionStatisticsPort::countOverview()['open']` unchanged (no new
  port method: "open" already means "not `published`/`abandoned`", exactly
  the sidebar's definition). `openNonConformities` reuses
  `NonConformityStatisticsPort::countNonConformitiesByStatus()`, summing the
  `open` and `in_progress` buckets (no new port method either).
  `submittedInterventions` (P4.1, the "to review" badge) uses the dedicated
  `InterventionStatisticsPort::countSubmitted()` — status `submitted`
  exactly — and reads `0` for anyone without the review permission, since
  the badge only means something to a member who may actually review.

- **Organization-scoped audit read / activity feed (P2.6)**:
  `GET /organizations/{organizationId}/audit-events`
  (`ListOrganizationAuditEventsHandler` +
  `ListOrganizationAuditEventsProvider`) exposes the organization's slice of
  the Audit module's tamper-evident ledger. This is the first Audit
  dependency in this module, and it goes through Audit's **published inbound
  capability** `Audit\Application\Port\Inbound\OrganizationAuditFeedPort`
  plus the `Audit\Application\Contract\OrganizationAuditEntry` type — the
  same producer-publishes-a-capability shape as this module's own
  `TeamDirectoryPort`. It deliberately does **not** inject Audit's
  `Application\Port\Outbound\AuditEventRepositoryPort`: no module here
  imports a sibling's outbound port, that port is Audit's own dependency on
  its persistence adapter, and holding it would hand this module the
  unfiltered ledger and make the organization scoping and the PII reduction
  rules a consumer has to remember rather than invariants the producer
  enforces. `deptrac` cannot catch that distinction — its layers are
  hexagonal, not per-module, so Application→Application is green either way.
  Filtering rides the dedicated
  nullable, indexed `audit_events.organization_id` column (auth migration
  `Version20260811120000`, backfilled from `metadata->>'organization_id'`,
  which `AuditEventSubscriber::recordOrganizationAudit()` has always written
  for every organization-scoped action) — `subjectId` only equals the
  organization id for the ~9 lifecycle actions, so the column is the only
  complete filter. Denial ordering lives in the handler and mirrors
  navigation counters: unknown organization → 404, non-member (or inactive
  member) → 404 (`OrganizationMemberNotFoundException` — deliberately not
  403, which would confirm the organization exists; the provider also
  collapses both causes into one shared 404 body, "Organization not
  found.", so the error detail cannot become an existence oracle either),
  active member without `organization.audit.read` → 403
  (`OrganizationAuthorizationPort::assertGrantedPermissions`), all before
  the ledger is read. The permission is admin-granted: NOT in
  `OrganizationSystemRoleCatalog`'s member role; admins hold it via the
  `organization.*` wildcard.

  **PII reduction is the Audit module's invariant, not this one's.**
  Organization admins are a lesser-trust audience than platform auditors, so
  — regardless of `SECURITY_LOG_INCLUDE_PII` — the payload carries no actor
  email, IP address/hash, user agent, client/tenant id, or chain internals;
  `OrganizationAuditEntry` has no field for any of them. Metadata is filtered
  by a **per-action allowlist** (`OrganizationAuditMetadataProjection`) whose
  default is drop, so a producer that adds an organization-scoped action
  without an entry degrades this feed rather than leaking through it. Both
  rules and the criteria for extending the allowlist live in
  `src/Audit/MODULE.md`; do not restate them here, and do not re-filter in
  this module — a second, weaker copy of a security rule is how the two drift.

  **Actor naming is membership-scoped, and this is a deliberate decision.**
  The ledger's actors are not all this organization's people: a platform
  operator acting on it is recorded here too. The handler therefore resolves
  `actorIsOrganizationMember` per distinct actor (a membership row in THIS
  organization, active or deactivated — a former colleague stays nameable, or
  the feed's history would go anonymous the moment someone leaves; bounded by
  the 100-row page and cached per invocation), and the provider resolves
  `actorDisplayName` via `GetUserQuery` only for those — the same
  per-request-cached pattern as `ListOrganizationInvitationsProvider`. For
  everyone else the opaque `actorId` is still published, because the
  organization is entitled to know something happened to it, but the name is
  not, and `actorDisplayName` stays `null`. **Null carries no label on
  purpose**: a backend-supplied string like "Platform operator" would be
  untranslatable in a frontend that ships fr/es/en, so the neutral placeholder
  is the frontend's to render. This also means `actorDisplayName` is null in
  two different situations (not nameable, and nameable but no user record) —
  acceptable, since the frontend renders the same placeholder for both.

  **Page size** is clamped twice: `PaginationExtractor` applies the shared
  500-row ceiling, then `ListOrganizationAuditEventsProvider::MAX_ITEMS_PER_PAGE`
  clamps to 100 (`min(500-clamped, 100)`), which is the binding constraint —
  an audit ledger grows without bound per organization and every row carries a
  metadata payload, so a page of 500 is far heavier here than 500 option rows.

  **Wiring**: `ListOrganizationAuditEventsHandler` is registered with
  `tags: ['messenger.message_handler']` in `config/modules/organization.yaml`
  and needs no `$entityManager` argument — it holds only ports, and the
  ledger read resolves through Audit's own wiring on the `auth` manager.
  Covered by `ListOrganizationAuditEventsHandlerTest` (denial ordering, the
  port call, membership-scoped naming), `ListOrganizationAuditEventsProviderTest`
  (filters, the 100-row clamp, the bus-unwrapping paths, and that a non-member
  actor is never even looked up) and `tests/Functional/Api/OrganizationAuditEventsApiTest`
  (401/403/404×2/200, cross-organization isolation, the metadata allowlist end
  to end, and the unnamed outside actor).

  This unlocks the frontend activity feed (P3.1).

## Teams (R9)

`Team` is a named grouping of organization members, scoped to an
organization and CRUD-mirroring `OrganizationRole`'s endpoint/permission
shape (see the endpoints table above). `TeamMember` is a record-level join
(`team_members`, composite PK `team_id`/`member_id`), not a domain
aggregate; its `role` field is a free-form label (e.g. `"lead"`),
**explicitly not an RBAC role** — do not confuse it with `OrganizationRole`.

- **Uniqueness**: `(organization_id, name)` is enforced by a DB unique
  constraint (`uniq_team_org_name`) plus a pre-check in
  `CreateTeamHandler`/`UpdateTeamHandler`, raising `TeamNameAlreadyExistsException`
  → HTTP 409.
- **Permissions**: `organization.teams.read` (all GET), `organization.teams.write`
  (create/update team, add/remove members — day-to-day), `organization.teams.manage`
  (delete team — lifecycle). The system `member` role is granted
  `organization.teams.read` via `OrganizationSystemRoleCatalog::permissionsFor()`,
  which propagates to existing organizations at READ time through
  `mergePermissions()` — no backfill migration needed. `admin` is covered by
  the `organization.*` wildcard.
- **Member-removal cleanup**: an organization member is soft-removed
  (`OrganizationMember::deactivate()`), so the `team_members.member_id`
  foreign-key CASCADE rarely fires. The real cleanup path is
  `Organization\Infrastructure\EventSubscriber\RemoveTeamMembershipsOnMemberRemovedHandler`,
  an intra-module Symfony event subscriber reacting to
  `organization.organization_member_removed_event` (the same
  `EventDispatcherPort` mechanism every other Organization domain event
  uses) that deletes every `team_members` row for the removed member across
  all of the organization's teams. Reactivating the member does **not**
  restore prior team memberships (acceptable, by design).
  `TeamDirectoryService::findActiveMemberIds` also filters to active
  members defensively as a second line of defense.
- **`TeamDirectoryPort`** (inbound, `Application/Port/Inbound/TeamDirectoryPort.php`,
  implemented by `TeamDirectoryService`): lets other modules resolve a
  team's ACTIVE membership without depending on Organization's Domain or
  Infrastructure layers — consumed directly cross-module exactly like
  `OrganizationAuthorizationPort`. `resolveTeam(organizationId, teamId)`
  returns a `TeamMembershipSnapshot` (or `null` when the team does not
  belong to that organization); `listActiveMemberIds(...)` is the
  convenience read used by Intervention's team-assignment endpoint today,
  and is forward-declared for the future Messaging channel↔team membership
  binding (not built yet).
- **Snapshot vs dynamic**: Intervention's `POST /interventions/{id}/team-assignments`
  (see `src/Intervention/MODULE.md`) expands a team's CURRENT active
  members into the intervention's `participants` list ONCE, at assignment
  time — a deliberate snapshot, not a live/dynamic link. A later
  team-membership change never mutates an already-assigned intervention.
  This keeps behavior deterministic under the offline/ETag optimistic-concurrency
  replay model and respects the schedule mutability guard
  (`Intervention::assertScheduleMutable()`): participants stay assignable
  through `planned`, `in_progress` and `changes_requested`, and only a
  `submitted`, `published` or `abandoned` intervention refuses the assignment.





