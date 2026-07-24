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
| GET | `/api/organizations/{id}` | Get one Organization (requires `Organization.read`) |
| DELETE | `/api/organizations/{id}` | Archive the organization (reversible soft delete — see Notes; **not** a permanent removal). Requires `organization.delete` plus the danger-zone confirmation: a `slug` query parameter matching the organization's current slug (case-insensitive, trimmed). Missing or mismatched confirmation → HTTP 422, nothing archived. Idempotent when already archived, provided the confirmation is still correct |
| PATCH | `/api/organizations/{id}` | Update general & branding settings (name, slug, description, status), the legal profile (`country`, `legalType`, `legalName`, `registrationNumber`, `vatNumber` — see below), plus the structured sections: `notifications`, `regional`, `compliance` (non-conformity SLA days per severity, inspection periodicity per equipment type, reminder window — map entries set to `null` revert to the catalog default from `OrganizationComplianceDefaults`; only customizations are persisted, effective values are resolved on read), `automation` (explicit opt-in toggles, e.g. `autoCreateInterventionOnCriticalNc`) , `approval` (R17 four-eyes policy: `actionRules` per gated action type — `enabled`/`minApproverRole`/`minSeverity`, `null` entry reverts to disabled —, `allowSelfApproval`, `approvalTtlDays`; every action type defaults to disabled) and `assistant` (AI-assistant policy: `enabled`, `model`, `temperature`, `includeBusinessContext`; disabled by default). Periodicity keys are validated against the Equipment catalog via `EquipmentTypeCatalogPort`; `approval.actionRules` keys are validated against the Approval catalog via `ApprovalActionTypeCatalogPort`. Requires `organization.settings.write` |
| GET | `/api/organizations/legal-types` | Reference catalog of organization legal entity type values/labels for the Legal profile settings tab select |
| POST | `/api/organizations/{organizationId}/logo` | Upload the organization logo (multipart). Requires `organization.settings.write` |
| GET | `/api/organizations/{organizationId}/logo.webp` | Stream the organization logo (public) |
| GET | `/api/organizations/{organizationId}/me` | Get the authenticated active member profile with resolved roles and effective permissions |
| GET | `/api/organizations/{organizationId}/dashboard` | Get lightweight Organization overview KPIs for cards, plus `trends` (per-KPI sparkline running-total series for facilities/members/equipment/inspections) and `recentInterventions` (the 5 most recently updated field interventions, org-scoped, gated by `organization.interventions.read`). `overview`, `alerts`, and non-`period*` KPIs are snapshots at `generatedAt`; `comparison` and `period*` KPIs follow `from`/`to` (filters: `from`, `to`, `compare`, `timezone`). `overview.nonConformities.severityLow`/`severityMedium`/`severityHigh`/`severityCritical` add an org-wide, ALWAYS-unfiltered by-severity breakdown across every status — see Notes (L3.10). Use dedicated `/dashboard/trends/*` endpoints for full chart series with custom granularity. Requires `organization.dashboard.read` plus members/roles/facilities/equipment/inspection read permissions. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/inspections` | Get the inspections-performed series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters. Requires `organization.inspection.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/equipment-created` | Get the equipment-created series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus `equipmentType` and `equipmentStatus`. Requires `organization.equipment.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/facilities-created` | Get the facilities-created series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus `facilityType`. Requires `organization.facilities.read`. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/non-conformities-opened` | Get the non-conformities-opened series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus an optional `metrics` filter (e.g. `metrics=non_conformities_resolved`) that adds the resolved series to the response's `seriesByMetric` map, sharing this call's resolved period/timezone/granularity — see Notes (L3.9). Requires `organization.inspection.read` per requested metric. |
| GET | `/api/organizations/{organizationId}/dashboard/trends/non-conformities-resolved` | Get the non-conformities-resolved series for a single chart with its own `from`/`to`/`granularity`/`timezone` filters, plus the same optional `metrics` combining filter (`metrics=non_conformities_opened`) — see Notes (L3.9). Requires `organization.inspection.read` per requested metric. |
| GET | `/api/organizations/{organizationId}/navigation-counters` | Get lightweight sidebar badge counters: `openInterventions` (excludes `published`/`abandoned`) and `openNonConformities` (`open` + `in_progress`). Caller must be an ACTIVE organization member; each counter individually falls back to `0` (never a 403) without the underlying `organization.interventions.read` / `organization.inspection.read` permission — see Notes (L3.11) |
| POST | `/api/organizations/{organizationId}/members` | Add member and assign role(s) |
| GET | `/api/organizations/{organizationId}/members` | List Organization members (each item carries `isOwner`, computed against the organization's `ownerUserId`) |
| POST | `/api/organizations/{organizationId}/invitations` | Invite member by email |
| GET | `/api/organizations/{organizationId}/invitations` | List Organization invitations |
| GET | `/api/organizations/invitations/{token}/preview` | Public preview of an invitation by token (organization, inviter, invited email, status, expiry) |
| POST | `/api/organizations/invitations/accept` | Accept an invitation token |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/revoke` | Revoke pending invitation |
| POST | `/api/organizations/{organizationId}/invitations/{invitationId}/resend` | Regenerate token, reset expiry and re-send the invitation email (returns a fresh accept link) |
| POST | `/api/organizations/{organizationId}/roles` | Create Organization role |
| GET | `/api/organizations/{organizationId}/roles` | List Organization roles (each item carries `memberCount`, the number of ACTIVE members currently assigned) |
| POST | `/api/organizations/{organizationId}/members/{memberId}/roles` | Assign role to member |
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

## Notes

- Auth identities stay in the auth database (`users`, tokens, sessions, etc.).
- Organization RBAC is contextual and evaluated through `OrganizationAuthorizationPort`.
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
- Plan quotas are enforced INSIDE the create/invite/add handlers, in the same
  transaction as the insert, serialized per (organization, resource) by a
  Postgres transaction-scoped advisory lock (`OrganizationQuotaLockPort`; no-op
  on non-Postgres platforms) so concurrent creates at the cap cannot both pass.
  `AddOrganizationMemberCommand::$enforceQuota` is false only on the
  invitation-accept path, which counts active members only
  (`assertCanAcceptMember`) so the accepted invitation never blocks itself.
- Role/permission writes are protected pre-dispatch by
  `OrganizationPermissionGrantGuardPort` (no privilege escalation) and
  `OrganizationLastAdminGuardPort` (no admin lockout, HTTP 409).
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
  (quotas) and, for the Compliance safety-register export, the
  `ComplianceExportEntitlementPort` allow-list (see `src/Compliance/MODULE.md`).
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
  `GET /organizations/legal-types` for the value/label catalog, following
  `OrganizationStatusResource`'s reference-catalog shape), `legalName` (free
  text, may differ from the display `name`), `registrationNumber` and
  `vatNumber` (the two previously-orphan value objects
  `Domain/ValueObject/OrganizationRegistrationNumber` /
  `OrganizationVatNumber`, length + `/^[A-Z0-9\-\/. ]+$/` validated). Unlike
  the settings sections above, these are plain nullable columns on
  `OrganizationRecord` (not part of the `settings` JSON blob) mapped directly
  on the `Organization` aggregate, mirroring how `description`/`logoUrl` are
  handled. The whole profile is optional — an organization with none of it
  set is valid — and the mockup's "complete/incomplete" badge is derived by
  the frontend from field presence, never persisted. Each of the five PATCH
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
  own `OrganizationAssistantSettings` value object. Only
  `includeBusinessContextFor()` is actually consumed so far
  (`GenerateAssistantReplyHandler` gates `AssistantPromptBuilder`'s
  business-context injection on it); `isEnabledFor()` remains declared but
  uncalled — see `src/Assistant/MODULE.md`'s "Deferred cross-module work".
  Fails closed (both methods return `false`) on an unknown/malformed
  organization, never leaking business context on a lookup failure.
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

- **Caller membership info on the organization list**: `GET /api/organizations`
  items expose `isOwner` and `roles` for the REQUESTING user (Account → Roles
  "Organization memberships" section; the org switcher ignores them).
  `ListUserOrganizationsHandler` resolves both in the Application layer:
  `isOwner` compares the organization's `ownerUserId` against the query's
  `userId` (no extra read — the aggregate is already loaded), and `roles`
  reuses the caller's membership already fetched by `findByUserId`, joining
  `OrganizationMemberRepositoryPort::findRoleIdsForMember` then
  `OrganizationRoleRepositoryPort::findByIdsInOrganization` per page item —
  the same repository join as `ListOrganizationMembers` /
  `GetCurrentOrganizationMemberProfile`, bounded by the page size. The fields
  ride on the shared `GetOrganizationResult`/`OrganizationOutput` as
  APPENDED NULLABLE members (`isOwner: ?bool`, `roles: ?list<{id, label}>`,
  where `label` is the role name), so every other constructor of those types
  (GetOrganization, the create/update/logo/plan processors, Onboarding) is
  untouched and keeps emitting `null` — `null` means "caller membership not
  resolved by this operation", while the list endpoint always emits concrete
  values (`false`/`[]` fallbacks in `ListUserOrganizationsProvider`). A
  member with no assigned role gets `roles: []`, never an error, and no role
  query is issued for it. Read-only aggregation: no schema change, no
  migration.

- **Sidebar navigation counters (L3.11)**: `GET /organizations/{organizationId}/navigation-counters`
  answers the "does this org have work waiting" badge question for the
  frontend sidebar without paying for the full `/dashboard` payload (KPIs,
  trends, comparisons). Access mirrors `GET /organizations/{id}/me`
  (`GetCurrentOrganizationMemberProfileHandler`): the caller only needs an
  ACTIVE membership, checked directly in `GetNavigationCountersHandler`
  (`OrganizationMemberRepositoryPort::findByOrganizationAndUser` +
  `isActive()`), not a specific permission — every member should see the
  sidebar badges. Each of the two counters is then individually soft-gated
  on the same permission its data already requires elsewhere
  (`organization.interventions.read` / `organization.inspection.read`,
  checked via `OrganizationAuthorizationPort::hasPermission`), degrading to
  `0` instead of a 403 for a member without that permission — the same
  pattern `GetOrganizationDashboardHandler` already uses for
  `recentInterventions`/`overview.interventions`. `openInterventions` reuses
  `InterventionStatisticsPort::countOverview()['open']` unchanged (no new
  port method: "open" already means "not `published`/`abandoned`", exactly
  the sidebar's definition). `openNonConformities` reuses
  `NonConformityStatisticsPort::countNonConformitiesByStatus()`, summing the
  `open` and `in_progress` buckets (no new port method either).

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
  replay model and respects the draft-only planning freeze
  (`Intervention::assertPlanningMutable()`).





