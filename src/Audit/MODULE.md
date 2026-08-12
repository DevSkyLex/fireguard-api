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
| GET | `/api/audit-events/export` | Streams a bounded CSV export of audit events | `audit.export` |

### Supported Filters

- `action`
- `actorType`
- `actorId`
- `actorEmailHash` (list/get filter only — the export endpoint does not accept
  it as a filter, see below; the CSV row still includes the
  `actor_email_hash` column, same as the JSON output)
- `subjectType`
- `subjectId`
- `clientId`
- `tenantId`
- `ipHash`
- `from` / `to` (ISO 8601)

### Export (CSV)

`GET /api/audit-events/export` (`AuditOperations::EXPORT`, permission `audit.export`
— distinct from `audit.read`, previously defined in the permission catalog but
unused until this endpoint) streams the ledger as CSV using the same filter set
as the list endpoint (`action`, `actorType`, `actorId`, `subjectType`,
`subjectId`, `clientId`, `tenantId`, `ipHash`, `from`/`to`), built by
`AuditEventExportCriteriaFactory` so an export always matches what the caller
is currently looking at.

- **Format — CSV, not PDF.** The ledger is an append-only, tabular event log
  that auditors filter/pivot in a spreadsheet — CSV is the natural fit and
  needs no template rendering. A PDF renderer already exists in this codebase
  (`Compliance\...\SafetyRegisterPdfRendererPort`), but it renders a
  plan-gated regulatory document (the "registre de sécurité") with its own
  layout and entitlement rules — a different concern from a raw ledger dump.
  Reusing it here would conflate the two.
- **Bounded, not unbounded.** `ExportAuditEventsHandler` runs a cheap
  `COUNT` against the filters first (`AuditEventRepository::countMatching()`)
  and rejects the request with HTTP 422 (`AuditExportTooLargeException`) when
  it matches more than `ExportAuditEventsHandler::MAX_EXPORT_ROWS` (50,000)
  events — before a single row is streamed. The full filter set (not only a
  date range) is supported, so the cap is enforced as a live match count
  rather than a mandatory `from`/`to` range: a narrow, dateless filter (e.g. a
  single `subjectId`) is still exportable, while a truly unbounded query is
  rejected with guidance to narrow the range or add a more specific filter.
- **Streamed, not materialized.** Under the cap, rows are produced by
  `AuditEventRepository::stream()` (Doctrine `toIterable()`) and written one
  row at a time by `AuditEventCsvWriter` straight to the HTTP response body
  (`StreamedResponse`) — the full result set is never held in memory as an
  array, so a 50,000-row export costs O(1) memory regardless of size.
- **PII posture — no new leak surface.** The CSV projects exactly the same
  fields already exposed by the JSON list/get endpoints, governed by
  `AuditPiiSanitizer` and `SECURITY_LOG_INCLUDE_PII` **at the time the event
  was recorded**: `actorEmail`/`ipAddress` are non-null in the export only
  when PII capture was enabled when that row was written; otherwise they are
  empty, exactly as in the JSON responses. The export applies no additional
  redaction and adds no field that isn't already part of that contract — it
  is not a bypass of the sanitizer, only a different serialization of the
  same governed data. `actorEmailHash`/`ipHash` (irreversible hashes) are
  always present regardless of the PII flag, same as today.
- **Self-auditing.** Every export dispatches `AuditEventsExportedEvent`,
  recorded by `AuditEventSubscriber::onAuditEventsExported()` as ledger action
  `audit.export_performed` (subject type `audit_export`) — the ledger
  auditing its own export, the same convention every other module's
  significant action follows. Its metadata carries `format`, `row_count`, and
  `filter_keys` (the applied filter **names** only, e.g. `["action", "from"]`)
  — never the raw filter values, since a filter such as `actorId`/`subjectId`
  can itself be person-identifying and this entry must not become a second,
  less-governed place where that value lands in the ledger.

## Architecture

- Presentation: Api Platform resources/providers/DTOs
- Application: Use cases for record + query
- Domain: Audit event model
- Infrastructure: Doctrine persistence + hash chaining service

## Recorded actions

`AuditEventSubscriber` listens to domain events from other modules
(dispatched through `EventDispatcherPort`, event name = `<module>.<snake_case_class>`)
and appends one ledger entry per action:

- `auth.*` — `login_success`, `login_failed`, `logout`, `token_issued`
- `oauth.*` — `token_issued`, `token_issue_failed`, `token_refreshed`,
  `token_refresh_failed`, `token_revoked`, `consent_granted` / `consent_updated`
- `otp.*` — `totp_enrolled`, `totp_disabled`
- `organization.*` — `created`, `archived`, `restored`, `suspended`,
  `settings_updated` (`changed_fields` in metadata), `role_created`,
  `role_updated`, `role_deleted`, `role_assigned` (also emitted when the
  add-member flow grants new roles to an already-active member),
  `role_unassigned`, `member_added` (with `role_ids`; on the invitation-accept
  path it is dispatched by the accept handler after ITS transaction commits so
  a rollback never leaves a phantom ledger row), `member_removed`,
  `invitation_sent` (covers resends, `resend` flag in metadata),
  `invitation_accepted` (with `role_ids`), `invitation_revoked` (`reason`:
  `manual` or `delivery_failed` for the automatic invalidation),
  `plan_changed` (skipped when the same plan is re-applied;
  `over_quota_resources` lists the caps the new plan puts below the current
  usage), and the refused
  security attempts `permission_grant_denied` (privilege escalation) and
  `last_admin_lockout_prevented`; `team_created`, `team_updated`,
  `team_deleted` (subject type `organization_team`; metadata: `name`
  where applicable) and `team_member_added` / `team_member_removed`
  (subject type `organization_team_member`, subject id = the member id;
  metadata: `team_id`, and `role` for the add case — the free-form
  membership label, not an RBAC role). The intervention team-assignment
  endpoint itself is not audited, consistent with intervention planning
  edits not being audited today.
- `inspection.*` — `submitted`, `closed`, `cancelled` (with `previous_status`),
  `non_conformity_recorded` (severity), `non_conformity_status_changed`
  (done/waived = resolution). Emitted by the use-case handlers and the
  canonical processor for PUBLISHED records only: draft intervention
  scratchpads never emit, and the intervention-adapter write path is
  deliberately deferred to the future `intervention.published` audit action
  because it runs inside the publication transaction (main DB) while the
  ledger commits independently (auth DB) — emitting there could record
  phantom rows on rollback
- `facility.*` — `archived`, `restored`, `moved` (previous/new parent) —
  same published-only and post-commit rules as the inspection slice
- `equipment.*` — `commissioned`, `under_maintenance`, `returned_to_stock`,
  `decommissioned` (each with `previous_status`) — same rules; the canonical
  processors collect their events during the wrapped mutation and dispatch
  after the commit
- `intervention.*` — `published` (the audit point for the intervention
  publication write path: the per-resource adapters never emit; drafts
  materialize here) and `publication_failed` (with `reason`), both emitted by
  `ExecutePublicationHandler` after `publish()`/`markFailed()` are durable.
  Failures before the intervention context resolves are not ledgered (no
  organization scope) but stay on the publication record. The Stripe webhook
  plan path needs no dedicated action: it dispatches
  `ChangeOrganizationPlanCommand` through the bus, so it lands in the ledger
  as `organization.plan_changed` with actor `system`. `status_transitioned`
  covers every intervention status change (metadata: `intervention_number`,
  `from_status`, `to_status`, and `review_note` when the target is
  `changes_requested`), emitted by
  `Intervention\Infrastructure\Adapter\Workflow\DoctrineInterventionWorkflowGatewayAdapter`
  — the single write path behind `PATCH /interventions/{id}` and the
  work-item-driven `planned -> in_progress` auto-start — deferred until its
  `wrapInTransaction` commits, mirroring the notification-deferral pattern
  already in that adapter; the actor is always the mutating user — the
  auto-start is attributed to the member whose work-item update triggered it
  (a `null` actor falling back to `system` is modeled by the event but has no
  production call site today).
- `maintenance.*` — `schedule_overridden` (`PATCH /maintenance/schedules/{id}`
  setting/clearing `intervalOverride`; metadata: `equipment_id`,
  `interval_override`) and `campaign_generated` (`POST /maintenance/campaigns`;
  subject is the created intervention, metadata: `work_items_count`), both
  emitted synchronously by their handlers with the acting user as actor.
- `intervention.recurrence_*` — `recurrence_created` (metadata: `template_id`),
  `recurrence_updated`, `recurrence_deleted` (all emitted synchronously by
  their handlers with the acting user as actor), and `recurrence_materialized`
  (emitted by `MaterializeDueRecurrencesHandler` for every due occurrence it
  processes, success or failure; metadata: `succeeded`, `intervention_id`,
  `error`; actor is always `system`, since the recurring sweep has no user).
- `automation.rule_*` — `rule_executed` (metadata: `rule_key`,
  `intervention_id`) and `rule_failed` (metadata: `rule_key`, `error`), both
  emitted by `ExecuteAutomationRuleHandler`; actor is always `system`
  (automations are never attributed to a user).
- `messaging.*` — `conversation_archived` (`PATCH /conversations/{id}`
  transitioning to archived; subject is the conversation, metadata:
  `subject_type`, `subject_id`) and `message_moderated` (a manager holding
  `organization.messaging.manage` deleting ANOTHER member's message; subject
  is the message, metadata: `conversation_id`, `author_member_id`), both
  emitted synchronously by their handlers with the acting user as actor.
  Deliberately NOT audited: per-message create/edit/self-delete (volume) —
  the ledger is a moderation trail, not a message-content log. v2 team
  channels add `channel_created` (metadata: `name`, `created_by_member_id`),
  `channel_participant_added` / `channel_participant_removed` (the channel
  access trail — metadata: `member_id`) and `channel_team_binding_changed`
  (metadata: `team_id`); subject is the channel conversation. These are
  channel-governance/access actions (bounded volume), distinct from the
  un-audited per-message activity.
- `import.job_*` — `job_completed` (subject is the import job; metadata:
  `kind`, `total_rows`, `successful_rows`, `failed_rows` — counts only, never
  row payloads) and `job_failed` (metadata: `kind`, `job_error`), both
  emitted by `ProcessImportJobHandler`; actor is the job's `createdBy` user
  (the request that created the import job), not `system` — consistent with
  other async/system-triggered actions attributing to the user who initiated
  them.
- `webhook.subscription_*` — `subscription_created` (subject is the webhook
  subscription; metadata: `url_host`, `event_types` — the target URL's host
  only, never the full URL or the signing secret) and `subscription_deleted`,
  both emitted synchronously by `CreateWebhookSubscriptionHandler` /
  `DeleteWebhookSubscriptionHandler` with the acting user as actor. Per-delivery
  attempts are deliberately NOT audited here (high volume, already tracked in
  `webhook_deliveries`); only subscription lifecycle changes are.
- `approval.*` — the four-eyes approval workflow lifecycle (subject is the
  approval request, subject type `approval_request`): `requested` (a
  regulated action deferred by the org's approval policy; metadata:
  `action_type`, `subject_id`, `requested_by_member_id`; actor is the
  requester), `approved` / `rejected` (metadata: `action_type`,
  `subject_id`, `decision_by_member_id`; actor is the deciding user),
  `expired` (the scheduled sweep transitioning a stale pending request;
  metadata: `action_type`, `subject_id`; actor is always `system`), and
  `execution_failed` (an approved request whose deferred action could no
  longer be re-executed because its subject changed state — e.g. the
  equipment was already deleted — transitioned to `cancelled`; metadata:
  `action_type`, `subject_id`, `error`; actor is the deciding user). All
  five emitted synchronously by `Approval\Application\UseCase\...` handlers.
- `compliance.register_exported` — every export of the regulatory "registre de
  sécurité" PDF (Compliance module, plan-gated); subject is the facility when
  the export is facility-scoped, otherwise the organization; metadata:
  `scope`, `plan_key`, `generated_at`; actor is the exporting user.
- `audit.export_performed` — every CSV export of this ledger itself
  (`GET /audit-events/export`, permission `audit.export`) — the ledger
  auditing its own export action. Not organization-scoped like most other
  actions above (an export may span tenants or match no `tenantId` filter at
  all): subject type `audit_export`, no subject id; metadata: `format`
  (`csv`), `row_count` (the matched/streamed count), and `filter_keys` (the
  applied filter **names** only — e.g. `["action", "from"]` — never the raw
  values, since a filter such as `actorId`/`subjectId` can itself be
  person-identifying); actor is the exporting user (falls back to `system`
  if resolved outside an authenticated request context). Emitted by
  `ExportAuditEventsController` after the bounded stream is built, before
  the response is returned.

Organization actions resolve their actor from the domain event when it
carries one (inviter, revoker, acceptor…), otherwise from the authenticated
security token, falling back to `system` for CLI/async paths. Their metadata
always includes `organization_id`, and invited emails are stored through the
PII sanitizer (masked value + hash).

The `organization_id` metadata value is also denormalized into a dedicated
nullable, indexed `audit_events.organization_id` column (auth migration
`Version20260811120000`, backfilled from `metadata->>'organization_id'`), so
organization-scoped reads filter on an index instead of a JSON scan. The
metadata copy remains the hash-covered source of truth — the column is
deliberately **not** part of the event-hash payload, keeping every row
(pre- and post-backfill) verifiable with the same payload recipe.
`RecordAuditEventHandler` enforces the coherence invariant on every write:
when the command carries an `organizationId`, it is synced into
`metadata['organization_id']` (overwriting any mismatched caller value), so
the column is always provably derived from a hash-covered field — no caller
can persist a column value the ledger's tamper-evidence does not cover.

`organization.ownership_transferred` is part of this set like the rest:
`recordOrganizationAudit()` gives it the column, so it appears in the
organization feed, and its payload (`previous_owner_user_id`,
`new_owner_user_id` — two opaque ids) is vetted in the projection below
rather than admitted by default.

`AuditEventSearchCriteria` accepts an `organizationId` filter for this; the
platform-level `/api/audit-events` HTTP filter set is unchanged (list and
export stay in sync).

### Published capability: the organization audit feed

`Audit\Application\Port\Inbound\OrganizationAuditFeedPort`
(implemented by `Application\Service\OrganizationAuditFeedService`, aliased in
`config/modules/audit.yaml`) is this module's **only** published read
capability, consumed directly cross-module in the same way
`Organization\Application\Port\Inbound\TeamDirectoryPort` is. Its sole
consumer today is the Organization module's
`GET /api/organizations/{organizationId}/audit-events` activity feed
(`organization.audit.read`).

It exists so that two invariants belong to this module rather than to whoever
reads it:

- **Scope.** The service builds the `AuditEventSearchCriteria` itself with
  `organizationId` always set. No argument combination reaches a query that
  spans organizations.
- **Reduction.** The port publishes `Application\Contract\OrganizationAuditEntry`,
  which simply has no field for actor email (plain or hashed), IP address or
  hash, user agent, client/tenant id, or the chain internals — they cannot be
  forgotten downstream because there is nowhere to put them. This holds
  regardless of `SECURITY_LOG_INCLUDE_PII`, which governs platform auditors,
  a different and higher-trust audience.

A consumer must **not** be given `Application\Port\Outbound\AuditEventRepositoryPort`
instead: that is this module's own dependency on its persistence adapter, it
hands over the unfiltered ledger, and it makes the reduction a rule someone
has to remember. Note that `deptrac.yaml` defines hexagonal layers with no
per-module layers, so an Application→Application import passes green — the
gate does not police this, review does.

**Metadata producer contract**: metadata dispatched through
`recordOrganizationAudit()` is potentially readable by organization admins.
It is filtered by `Application\Service\OrganizationAuditMetadataProjection`,
a **per-action allowlist**, and the default is *drop*: an action absent from
the map publishes an empty payload, and a key absent from its action's entry
is dropped. A denylist of key names was tried first and is unsound twice
over — it cannot see inside a value, and it admits by default whatever a
future producer invents.

So: **adding an organization-scoped action without an entry in that map
degrades the feed rather than leaking through it.** When extending the map,
admissible values are identifiers, catalog/enum keys, booleans, counts, and
organization-owned entity labels. Not admissible: operator-typed prose
(`reason`, `context`), system internals (`error`, `job_error` — exception
text embeds file paths and row fragments), personal data (emails, IPs, user
agents), and anything belonging to another permission's surface
(`invited_email` → `organization.members.manage`, `url_host` →
`organization.webhooks.*`); the feed must never become a way around a
permission the caller was not granted. `organization_id` is dropped
everywhere — the reader already scoped the request to one organization.

`OrganizationAuditMetadataProjectionTest` holds a standing guard over the
whole map, so a future entry cannot quietly admit a prose or credential key.

## Configuration

- Audit events are stored in `audit_events` and `audit_event_chains`.
- `Audit\Application\Port\Inbound\OrganizationAuditFeedPort` is aliased to
  `Audit\Application\Service\OrganizationAuditFeedService` in
  `config/modules/audit.yaml`. It reads through `AuditEventRepositoryPort` and
  touches no entity manager of its own, so it names none;
  `AuditEventRepository` resolves the default manager, which is `auth` — the
  database `audit_events` lives in.
- PII handling uses the same flags as security logs:
  - `SECURITY_LOG_INCLUDE_PII`
  - `SECURITY_LOG_PII_SALT`
- Export row cap: `ExportAuditEventsHandler::MAX_EXPORT_ROWS` (50,000),
  enforced in code, not an env var.

## Testing

- Unit: `tests/Unit/Audit` — includes `ExportAuditEventsHandlerTest` (row-cap
  enforcement + streamed result), `AuditEventCsvWriterTest` (CSV shape, JSON
  metadata cell, no PHP `NULL` leakage), `AuditEventExportCriteriaFactoryTest`
  (filter parsing, blank-string handling, applied-filter-name extraction) and
  an `AuditEventSubscriberTest` case for `onAuditEventsExported` asserting
  the recorded metadata never carries raw filter values. Also
  `OrganizationAuditMetadataProjectionTest` (fail-closed on an unknown action,
  every free-text/PII key the producers actually emit named one by one, and
  the standing guard over the whole map) and `OrganizationAuditFeedServiceTest`
  (the scope invariant, and the reduction proven on a row saturated with PII).
- Integration: `tests/Integration/Audit/Infrastructure/Persistence/Doctrine/Repository/AuditEventRepositoryTest`
  runs `AuditEventRepository::countMatching()`/`stream()` against a real
  entity manager (a mocked QueryBuilder never parses the DQL those methods
  build) — combined filters + date-range filters, run on PostgreSQL, the
  same engine as production. `append()`'s chain-row upsert
  (`ON CONFLICT … DO NOTHING`) and its `SELECT … FOR UPDATE` row lock are
  likewise exercised on the real engine by every test that appends an event.
- Functional: `tests/Functional/Api/AuditApiTest` (list/get/export endpoints
  exist and require authentication; the export test also asserts the full
  list-endpoint filter set is accepted on the export route).

## Error Codes

- `EntityNotFoundException` -> audit event not found
- `AuditExportTooLargeException` -> 422, export filters match more than
  `ExportAuditEventsHandler::MAX_EXPORT_ROWS` events; narrow the filters and
  retry
