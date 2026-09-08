# Calendar Module

## Overview

Two capabilities:

- **A. Standalone events** — full CRUD on org-scoped `calendar_events`
  (title, description, start, end, all-day flag, optional facility, creator
  member). This is what makes the calendar UI's "New event" button work.
- **B. A unified feed** — a single, bounded, date-ranged read that merges
  standalone events with three read-only cross-module sources: Inspection,
  Intervention, and Maintenance. **There is no "Audit" category** — no generic
  cross-module audit-log feed is wired into the calendar, and none is planned.
  (This paragraph used to justify the absence against a design mockup. That
  mockup was deleted on 2026-08-09 and is no longer a reference for anything,
  so the gap is stated on its own terms.) Only these four sources ever
  contribute:
  - `calendar_event` — standalone events (this module's own data)
  - `inspection` — inspections whose `performedAt` falls in range
  - `intervention` — interventions whose `plannedStartAt` or `dueAt` falls in range
  - `maintenance` — preventive-maintenance schedules whose `nextDueAt` falls in range

This module never depends on the Organization or Facility ORM mappings:
`organization_id`, `facility_id`, and `created_by_member_id` are plain string
columns/fields throughout every layer (Domain, Infrastructure, Application),
mirroring `Automation\Infrastructure\Persistence\Doctrine\Record\AutomationRunRecord`'s
precedent.

## Status: activated (L2.8)

Lot **L2.0** (the wave-2 `main` schema lot) scaffolded this module's
touchpoints only. Lot **L2.8** activates it: standalone calendar events (full
CRUD) plus a unified, date-ranged feed merging standalone events with
inspections, interventions, and preventive-maintenance due dates.

## API Endpoints

| Method | Path | Operation | Permission |
| --- | --- | --- | --- |
| POST | `/organizations/{organizationId}/calendar/events` | `createCalendarEvent` | `organization.events.write` |
| GET | `/organizations/{organizationId}/calendar/events/{eventId}` | `getCalendarEvent` | `organization.events.read` |
| PATCH | `/organizations/{organizationId}/calendar/events/{eventId}` | `updateCalendarEvent` | `organization.events.write` |
| DELETE | `/organizations/{organizationId}/calendar/events/{eventId}` | `deleteCalendarEvent` | `organization.events.write` |
| GET | `/organizations/{organizationId}/calendar/feed?from=...&to=...` | `getCalendarFeed` | `organization.events.read` |
| POST | `/organizations/{organizationId}/calendar/feed-token` | `createCalendarFeedToken` | `organization.events.read` |
| GET | `/organizations/{organizationId}/calendar/feed-token` | `getCalendarFeedToken` | membership (own token only) |
| DELETE | `/organizations/{organizationId}/calendar/feed-token` | `deleteCalendarFeedToken` | membership (own token only) |
| GET | `/calendar/feed/{token}.ics` | `getCalendarFeedIcs` | **none — public**, the URL-embedded secret is the credential |

The original `PATCH` implementation treated an omitted (`null`) property as
corresponding field unchanged, mirroring
`Webhook\Application\UseCase\Command\Subscription\UpdateWebhookSubscription\UpdateWebhookSubscriptionCommand`.
Consequently, `description`, `endsAt`, and `facilityId` cannot be cleared
back to `null` once set through this endpoint — accepted as the same
limitation `UpdateWebhookSubscriptionCommand` carries for `description`.

The current implementation supersedes that limitation: omitted fields remain
unchanged, while explicit `null` values clear `description`, `endsAt`, and
`facilityId`. Request-field presence is carried separately from the DTO value
so `null` is never confused with omission.

There is no standalone-events *list* endpoint distinct from the feed: the
unified feed (with `sourceKey=calendar_event` items) **is** the list view.
`GET .../calendar/events/{eventId}` exists only to fetch one event's full
detail (e.g. to populate an edit form).

### `GET .../calendar/feed`

- `from`/`to` are **mandatory** ISO-8601 datetimes with an explicit timezone
  offset. Both missing/blank → `400`.
- The range is **bounded**: inverted (`from` after `to`) or over-366-days
  ranges → `400`. This mirrors
  `Organization\Application\Support\DashboardSeriesBuilder::MAX_TREND_PERIOD_DAYS`
  — an unbounded calendar feed would eventually try to load every
  intervention/inspection/maintenance schedule ever created for a busy
  organization.
- Each of the four sources is also capped per-call
  (`CalendarFeedAggregator::PER_SOURCE_LIMIT` = 500 items), pushed down into
  the query, never a post-fetch truncation.
- Items are merged and sorted deterministically: `startsAt` ascending, tied
  by `sourceKey` ascending, tied by `id` ascending — never dependent on
  source call order.
- **A failing source never fails the whole feed.** Each of the four sources
  is called defensively; a throwing source degrades to "contributed
  nothing" and is logged at `error` level, mirroring
  `Notification\Application\Service\InboxAggregator`.
- `status` on a feed item is always the source's raw enum value (e.g.
  `overdue`, `closed`, `draft`), never a translated label — the frontend
  owns labels/colors via its own per-domain tag registries.

### Member iCal subscription (`feed-token` + `GET /calendar/feed/{token}.ics`)

Phase 10a. Lets Outlook/Google/Apple Calendar subscribe to a member's
unified feed **without a session**: the member creates a personal token
(`POST .../calendar/feed-token`, requires `organization.events.read`), and
the returned URL — the **only** response ever carrying the raw secret — is
pasted into the calendar client.

- **One active token per (organization, member).** `POST` creates *or*
  regenerates: any previously active token is revoked first (`rotated: true`
  in the response). `GET` returns metadata only (`createdAt`, `lastUsedAt` —
  never the secret nor its hash); `DELETE` revokes (204). Both answer 404
  when the member has no active token.
- **The secret**: 32 bytes CSPRNG, base64url (43 chars, URL-safe) —
  `CalendarFeedTokenSecretFactory`. Only its SHA-256 hex hash is persisted
  (`member_calendar_feed_tokens.token_hash`, unique); the raw value lives in
  `RotateCalendarFeedTokenResult` for the duration of one response.
- **The `.ics` endpoint is public** (`PUBLIC_ACCESS` in
  `config/packages/security.yaml`, GET only — same mechanism as the public
  invitation preview) and rate limited per IP (`limiter.calendar_feed`,
  30/min sliding window). Lookup is by hash; an unknown token, a revoked
  token, and a token whose member lost `organization.events.read` all answer
  the **same plain 404** — no oracle.
- **The member's permissions apply.** The controller resolves the token
  (`ResolveCalendarFeedTokenQuery`) then dispatches the exact same
  `GetCalendarFeedQuery` the interactive feed runs, with the token member's
  `userId` — so `GetCalendarFeedHandler`'s `organization.events.read` check
  and the aggregator's visibility rules are reused unchanged.
- **Window**: fixed, now−30 days → now+180 days (computed in
  `ResolveCalendarFeedTokenHandler`, well under the feed's 366-day cap).
- **`lastUsedAt` is throttled**: persisted at most once per hour
  (`CalendarFeedToken::shouldRecordUsage()`), so polling clients do not turn
  every fetch into a write.
- **Format**: hand-written RFC 5545 (`CalendarFeedIcalWriter`, no Composer
  dependency): VCALENDAR/VEVENT, stable `UID` (`<sourceKey>-<id>@fireguard`),
  `DTSTART`/`DTEND` (`VALUE=DATE` + non-inclusive end for all-day items, UTC
  datetimes otherwise), type-prefixed `SUMMARY` (`[Inspection] …`), short
  `DESCRIPTION` (≤300 chars), `X-FIREGUARD-STATUS` with the raw status, and
  a deep `URL` into the frontend calendar page
  (`{APP_FRONTEND_URL}/organizations/{orgId}/calendar?target=…&id=…`).
  TEXT escaping (backslash/semicolon/comma/newline) and 75-octet line
  folding on UTF-8 boundaries per RFC 5545 §3.1.
- **Response headers**: `Content-Type: text/calendar; charset=utf-8`,
  `Cache-Control: private, max-age=300`, `X-Robots-Tag: noindex`.
- **Audit**: `calendar.feed_token_created` (metadata: `rotated`) and
  `calendar.feed_token_revoked` (metadata: `reason` = `revoked`|`rotated`)
  via `CalendarFeedTokenCreatedEvent`/`CalendarFeedTokenRevokedEvent` —
  identifiers only, never the secret nor its hash.
- **Known residual**: the secret sits in the URL path, so it can surface in
  intermediary/proxy access logs and browser history. Inside the app there
  is no access-log middleware and the domain-event security log carries
  event names only; the one in-app path that can echo the URL is Symfony's
  `request`-channel "Matched route" line — in **prod** it stays inside the
  `fingers_crossed` buffer and is flushed only when a 5xx occurs during the
  request (404s are `excluded_http_codes`), in **dev** it lands in the debug
  log like every route. The response is `Cache-Control: private`, and
  rotation/revocation are one call away.
- **Known residual**: the per-fetch `organization.events.read` re-check goes
  through `OrganizationAuthorizationService`'s shared permission cache
  (30-second TTL), so a member removed from the organization can keep
  fetching the feed for up to ~30 seconds. Bounded, platform-wide behavior of
  the authorization cache — not specific to this endpoint; the token itself
  is revocable immediately.

## Domain Model

- `Domain/Model/Event/CalendarEvent` — mutable aggregate (`create()` /
  `reconstitute()` factories, mirroring
  `Webhook\Domain\Model\Subscription\WebhookSubscription`). Enforces one
  invariant: `endsAt` (when set) must not be before `startsAt` —
  `CalendarEventValidationException::endBeforeStart()`.
- `Domain\ValueObject\CalendarEventId` — UUID value object.
- `Domain\Event\CalendarEventCreatedEvent` / `CalendarEventUpdatedEvent` /
  `CalendarEventDeletedEvent` — dispatched on create/update/delete, each
  consumed by `Audit\Infrastructure\EventSubscriber\AuditEventSubscriber`
  (actions `calendar.event_created` / `calendar.event_updated` /
  `calendar.event_deleted`; see `Audit\MODULE.md`). `CalendarEventUpdatedEvent`
  was added later than `Created`/`Deleted` — until then this module diverged
  from `Webhook\Domain\Event\Subscription`'s Created/Deleted-only precedent by
  omission (calendar writes left no audit trail), not by design.
- `Domain\Exception\CalendarEventNotFoundException` → 404,
  `CalendarEventValidationException` → 422.
- `Domain/Model/FeedToken/CalendarFeedToken` — mutable aggregate for the
  member iCal token (`create()`/`reconstitute()`), holding only the SHA-256
  hash. Owns the revocation idempotency and the one-write-per-hour
  `lastUsedAt` throttle (`shouldRecordUsage()`).
- `Domain\ValueObject\CalendarFeedTokenId` — UUID value object.
- `Domain\Event\CalendarFeedTokenCreatedEvent` / `CalendarFeedTokenRevokedEvent`
  — consumed by `AuditEventSubscriber` (actions `calendar.feed_token_created`
  / `calendar.feed_token_revoked`); identifiers only, never secret material.
- `Domain\Exception\CalendarFeedTokenNotFoundException` → 404 (declared in
  `api_platform.exception_to_status`); deliberately one exception for
  unknown *and* revoked *and* permission-lost, so the public endpoint leaks
  nothing.

## Application Layer

### Standalone events (Area: `Event`)

- `UseCase/Command/Event/{CreateCalendarEvent,UpdateCalendarEvent,DeleteCalendarEvent}`
- `UseCase/Query/Event/GetCalendarEvent`
- `Port/Outbound/Event/CalendarEventRepositoryPort` — `save`/`remove`/`findById`/`listBetween`.
- `Port/Outbound/Member/CalendarMemberDirectoryPort` — resolves the acting
  user's organization member id (the event's `createdByMemberId`) through
  Organization; a client can never supply its own member id. `CreateCalendarEventHandler`
  throws `CalendarEventValidationException` when the acting user has no
  active membership (422, not 403 — the permission check already passed;
  this is a data-integrity guard, not an authorization one).

### Unified feed (Area: `Feed`)

- `UseCase/Query/Feed/GetCalendarFeed` — `GetCalendarFeedHandler` asserts
  `organization.events.read`, parses/validates the mandatory `from`/`to`
  bounds (ISO-8601 with explicit timezone offset, non-inverted, ≤ 366 days),
  then delegates to `CalendarFeedAggregator`. Range parsing/validation is
  business logic and stays in the handler — the provider only extracts raw
  query strings, mirroring `GetOrganizationDashboardHandler`/`GetOrganizationDashboardProvider`.
- `Application/Contract/Feed/CalendarFeedItem` — the plain, framework-free
  contract type every source (standalone events + the three cross-module
  ports) maps into. Never a provider module's Domain object.
- `Application/Port/Outbound/Feed/{Inspection,Intervention,Maintenance}CalendarFeedPort`
  — three **named, module-specific ports** (not a tagged-iterator seam),
  mirroring the dashboard statistics ports
  (`Organization\Application\Port\Outbound\InterventionStatisticsPort` and
  siblings): the unified feed always has exactly these three external
  sources plus Calendar's own standalone events, so a fixed set of typed
  ports is clearer than a dynamic tagged collection.
- `Application/Service/CalendarFeedAggregator` — merges all four sources.
  Mirrors `Notification\Application\Service\InboxAggregator`'s resilience
  pattern (each source wrapped in try/catch + `LoggerPort::error()`) and
  deterministic-tie-breaker sort, adapted from descending (`occurredAt`, for
  an inbox) to ascending (`startsAt`, for a calendar).

## Cross-module ports & their adapters

| Port (owned by Calendar) | Adapter | Hosted in / registered in |
| --- | --- | --- |
| `Port\Outbound\Member\CalendarMemberDirectoryPort` | `OrganizationCalendarMemberDirectoryAdapter` | `Organization\Infrastructure\Adapter\Calendar\` — registered in `config/modules/organization.yaml`, aliased in `config/modules/calendar.yaml` |
| `Port\Outbound\Feed\InspectionCalendarFeedPort` | `InspectionCalendarFeedAdapter` | `Inspection\Infrastructure\Adapter\Calendar\` — registered in `config/modules/inspection.yaml`, aliased in `config/modules/calendar.yaml` |
| `Port\Outbound\Feed\InterventionCalendarFeedPort` | `InterventionCalendarFeedAdapter` | `Intervention\Infrastructure\Adapter\Calendar\` — registered in `config/modules/intervention.yaml`, aliased in `config/modules/calendar.yaml` |
| `Port\Outbound\Feed\MaintenanceCalendarFeedPort` | `MaintenanceCalendarFeedAdapter` | `Maintenance\Infrastructure\Adapter\Calendar\` — registered in `config/modules/maintenance.yaml`, aliased in `config/modules/calendar.yaml` |

Each adapter lives in the **provider** module (never in Calendar), per this
repo's cross-module convention (mirrors
`Facility\Infrastructure\Adapter\Organization\FacilityStatisticsAdapter` and
siblings). Calendar depends only on its own port interfaces and its own
`CalendarFeedItem` contract type — never the provider modules' Domain,
Infrastructure, or a concrete Adapter class.

- **`InspectionCalendarFeedAdapter`** reuses
  `Inspection\Application\Port\Outbound\InspectionRepositoryPort::findByOrganizationId()`
  (already filters to `recordStatus = 'published'`, already supports the
  `performedAt` range + sort + hard-limit needed here) — no new DQL. `title`
  embeds the inspector's free-form display name (raw data, not a translated
  label).
- **`InterventionCalendarFeedAdapter`** queries `InterventionRecord` directly
  (main entity manager), mirroring `InterventionStatisticsAdapter`: an
  intervention occurs on the calendar when either its `plannedStartAt` or
  its `dueAt` falls within range (`COALESCE(plannedStartAt, dueAt)` resolves
  the occurrence instant; `dueAt` surfaces as the feed item's `endsAt` only
  when it differs from the resolved start — compared via `getTimestamp()`,
  never object identity/equality, since two `DateTimeImmutable` instances
  representing the same instant are never `===`/`!==`-equal). Interventions
  with neither date set never appear. DQL's `ORDER BY` grammar rejects a bare
  `COALESCE(...)` call (only path expressions, arithmetic expressions, and
  result variables are accepted there — confirmed by the integration test
  below, which caught this at parse time); the occurrence instant is instead
  projected as `COALESCE(intervention.plannedStartAt, intervention.dueAt) AS
  HIDDEN occurrenceInstant` in the `SELECT` clause and then referenced by
  that alias in `ORDER BY`. `HIDDEN` keeps `getResult()` returning plain
  `InterventionRecord` objects rather than mixed entity/scalar rows.
- **`MaintenanceCalendarFeedAdapter`** queries `MaintenanceScheduleRecord`
  directly (main entity manager), mirroring `EquipmentMaintenanceDueStatusAdapter`
  — neither `MaintenanceScheduleRepositoryPort::list()` nor
  `listDueForCampaign()` offers a plain `nextDueAt` range across every due
  status, and every due status (not just `due_soon`/`overdue`) is included:
  navigating the calendar to a past date range should still surface
  schedules that were due then. `title` is a fixed, source-neutral phrase
  ("Preventive maintenance due") — no equipment name is resolved (that would
  require a per-row cross-module call into Equipment, the exact N+1 this
  adapter must avoid); the frontend deep-links via `targetId` (the schedule
  id) for the full detail.

## Persistence

Tables (**main** database). `member_calendar_feed_tokens` was created by
`Version20260828010000`:

- `member_calendar_feed_tokens` — id, `organization_id`, `user_id`,
  `token_hash` (SHA-256 hex, **unique**), `created_at`, `last_used_at`
  (nullable), `revoked_at` (nullable). Index `(organization_id, user_id)`.
  Plain identifier columns, no foreign keys — `user_id` points at the
  **auth** database and no key may cross that line.

`calendar_events` was created by `Version20260718124213`:

- `calendar_events` — id, `organization_id`, `title`, `description`
  (nullable), `starts_at`, `ends_at` (nullable), `all_day` (default false),
  `facility_id` (nullable), `created_by_member_id`, `created_at`,
  `updated_at`. Index `(organization_id, starts_at)`. No foreign key to
  `organizations`/`facilities` — mirrors `automation_runs`/
  `approval_requests`.

**Documented gap**: `facility_id` is not covered by
`Facility\Application\Service\FacilityArchivalGuard` — Calendar has no
outbound dependency port and never blocks facility archival. This is
deliberate: calendar reads are display-only (the module never writes back to
a facility), so an event referencing an archived facility is harmless — it
simply keeps showing on the calendar.

Doctrine record: `src/Calendar/Infrastructure/Persistence/Doctrine/Record/
CalendarEventRecord.php` (unchanged since L2.0).

Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`.

`CalendarEventRepository::listBetween()` overlap query: an event overlaps
`[from, to]` when `startsAt <= to AND COALESCE(endsAt, startsAt) >= from`.
Plain comparisons + `COALESCE` — the module needs no raw PostgreSQL SQL to
express the overlap, unlike the day-bucketing repositories.

## Permissions

`organization.events.read` / `organization.events.write`
(`Organization\Domain\Catalog\OrganizationPermissionCatalog`, added by L1.0
— not touched by this lot). `organization.events.read` is included in the
`member` system role's canonical permission set; `organization.events.write`
is not (mirrors `organization.webhooks.*`'s admin/integration-only stance,
though events are a lower-stakes write than webhooks — the write permission
is still deliberately withheld from the base `member` role and must be
granted explicitly).

## Configuration

- Service wiring: `config/modules/calendar.yaml` — the two repositories
  (main entity manager), the four cross-module port aliases above,
  `CalendarFeedTokenRepositoryPort`, `CalendarFeedAggregator`,
  `CalendarFeedTokenSecretFactory`, `CalendarFeedIcalWriter`
  (`$frontendUrl: '%app.frontend_url%'`), and the nine command/query handler
  `messenger.message_handler` tags.
- Cross-module adapter registrations: `config/modules/organization.yaml`,
  `config/modules/inspection.yaml`, `config/modules/intervention.yaml`,
  `config/modules/maintenance.yaml` (see the table above).
- Security: the event and feed-token management resources carry
  resource-level `security: "is_granted('ROLE_USER')"` plus the fine-grained
  `organization.events.*` permission checks inside the handlers, mirroring
  every other organization-scoped resource in this repo (e.g. Webhook). The
  public `.ics` route has an explicit `access_control` rule
  (`^/api/calendar/feed/[^/]+\.ics$` → `PUBLIC_ACCESS`, GET only) in
  `config/packages/security.yaml`.
- Rate limiting: `limiter.calendar_feed` (per IP, 30/min sliding window) in
  `config/packages/rate_limiter.yaml`, enforced in
  `GetCalendarFeedIcsController`.
- Doctrine mapping / import: `config/packages/doctrine.yaml` /
  `config/packages/modules.yaml` (unchanged since L2.0).

## Testing

- Unit:
  - `tests/Unit/Calendar/Application/UseCase/Command/Event/CreateCalendarEvent/CreateCalendarEventHandlerTest.php`
  - `tests/Unit/Calendar/Application/UseCase/Query/Feed/GetCalendarFeed/GetCalendarFeedHandlerTest.php`
  - `tests/Unit/Calendar/Application/Service/CalendarFeedAggregatorTest.php` —
    covers the merge/sort and the "one failing source degrades to empty,
    others still return" resilience contract.
  - `tests/Unit/Calendar/Presentation/Api/Processor/Event/CreateCalendarEventProcessorTest.php`
  - `tests/Unit/Calendar/Presentation/Api/Provider/Feed/GetCalendarFeedProviderTest.php`
  - `tests/Unit/Inspection/Infrastructure/Adapter/Calendar/InspectionCalendarFeedAdapterTest.php`
    (mocks `InspectionRepositoryPort`, already covered by real DQL elsewhere)
- Integration (real DQL, executed against a live entity manager — a mocked
  QueryBuilder never parses DQL):
  - `tests/Integration/Calendar/Infrastructure/Persistence/Doctrine/Repository/CalendarEventRepositoryTest.php`
    (the `listBetween` overlap query)
  - `tests/Integration/Intervention/Infrastructure/Adapter/Calendar/InterventionCalendarFeedAdapterTest.php`
    (the `OR`/`COALESCE` occurrence-date query)
  - `tests/Integration/Maintenance/Infrastructure/Adapter/Calendar/MaintenanceCalendarFeedAdapterTest.php`
  - `tests/Unit/Calendar/Domain/Model/FeedToken/CalendarFeedTokenTest.php`
    (revocation idempotency, hourly usage throttle)
  - `tests/Unit/Calendar/Application/Service/CalendarFeedTokenSecretFactoryTest.php`
    (43-char base64url secret, SHA-256)
  - `tests/Unit/Calendar/Application/UseCase/Command/FeedToken/…` —
    `RotateCalendarFeedTokenHandlerTest` (permission gate, only the hash is
    persisted, rotation revokes + audits both sides) and
    `RevokeCalendarFeedTokenHandlerTest` (404 with no active token, no event
    on the failure path)
  - `tests/Unit/Calendar/Application/UseCase/Query/FeedToken/…` —
    `GetCalendarFeedTokenMetadataHandlerTest` (structural no-secret pin) and
    `ResolveCalendarFeedTokenHandlerTest` (hash lookup, uniform not-found,
    −30/+180 window, throttled `lastUsedAt` writes)
  - `tests/Unit/Calendar/Presentation/Api/Ical/CalendarFeedIcalWriterTest.php`
    — structural RFC 5545 lint: framing, CRLF, TEXT escaping, stable UID,
    all-day `VALUE=DATE` with non-inclusive end, 75-octet folding on UTF-8
    boundaries.
- Functional: `tests/Functional/Api/CalendarApiTest.php` and
  `tests/Functional/Api/CalendarFeedTokenApiTest.php` (endpoint existence +
  authentication-required, and the public `.ics` route answering a uniform
  404 — never 401 — for an unknown token).
- E2E: `tests/E2E/CalendarFeedTokenFlowTest.php` — the full lifecycle:
  201 with the secret shown once, metadata without secret, unauthenticated
  `.ics` 200 with the member's entries, `lastUsedAt` recorded, rotation
  killing the old secret, revocation, and the uniform 404 afterwards.

**Uncovered by tests**: nothing engine-specific. The module's DQL uses only
plain comparisons and `COALESCE`, and the suite runs on PostgreSQL — the same
engine as production — so the integration suite above exercises the real
query plan.
