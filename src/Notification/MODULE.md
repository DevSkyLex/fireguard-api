# Notification Module

## Overview

Notification provides a generic internal notification system usable by other
modules through an inbound port. It supports multiple delivery channels and
exposes API endpoints for authenticated users to read their notifications.

Main goals:

- Persist notifications for traceability/audit.
- Deliver through configured channels (`email`, `mercure`), honoring each
  user's per-category delivery preferences.
- Expose user-facing read APIs (`list`, `get`, `mark as read`,
  `preferences`).

## API Endpoints

| Method | Path | Description | Handler |
| --- | --- | --- | --- |
| GET | `/api/notifications` | Paginated list of notifications for the authenticated user (`unreadOnly`, `type`, `category`, `organization`, `page`, `itemsPerPage`) | `ListNotificationsProvider` |
| GET | `/api/notifications/subscription` | Get Mercure subscriber JWT and SSE topic for the authenticated user | `GetMercureSubscriptionProvider` |
| GET | `/api/notifications/unread-count` | Unread notification count for the authenticated user (optional `organization` filter) | `GetUnreadNotificationsCountProvider` |
| PATCH | `/api/notifications/read-all` | Marks every unread notification of the authenticated user as read (optional `organization` filter); idempotent, single bulk update; returns the affected count | `MarkAllNotificationsAsReadProcessor` |
| GET | `/api/notifications/preferences` | Returns the authenticated user's customized per-category delivery preferences | `GetNotificationPreferencesProvider` |
| PATCH | `/api/notifications/preferences` | Upserts one or more per-category preferences for the authenticated user; returns the full customized set | `UpdateNotificationPreferencesProcessor` |
| GET | `/api/notifications/{id}` | Get one notification owned by authenticated user | `GetNotificationProvider` |
| PATCH | `/api/notifications/{id}/read` | Mark one notification as read (idempotent) | `MarkNotificationAsReadProcessor` |
| GET | `/api/inbox` | Unified, cursor-paginated inbox feed merging every registered `inbox.source_provider` source (`organization`, `before`, `limit`) | `GetInboxProvider` |

`/subscription`, `/unread-count`, `/read-all` and `/preferences` are declared
before the `/{id}` routes in `NotificationResource`; otherwise they would be
swallowed by the `{id}` placeholder. `GET /api/inbox` deliberately lives on
its own top-level `InboxResource` (`routePrefix: /inbox`), not nested under
`/notifications`, so it never has to compete with that ordering constraint.

## Per-User Notification Preferences

Every user can customize delivery per category (the `{category}` half of a
`{category}.{action}` notification type) through `GET`/`PATCH
/api/notifications/preferences`. A preference is per-user and global — it is
never scoped to an organization, and there is no admin path to read or write
another user's preferences.

Storage model (`notification_preferences` table, main database, composite PK
`(user_id, category)`, see [Persistence](#persistence)):

- **Absent row means "everything enabled."** Only categories a user actually
  customized are stored; a brand-new user has no rows and receives every
  notification on every channel. Rows are never backfilled.
- **An unknown category defaults to enabled**, not disabled.
  `NotificationType::isValid()` is advisory on purpose (the module accepts
  unknown types for forward-compatibility), and preference lookups never use
  it as a gate — the enforcement seam only ever asks "is there a customized
  row for this exact category?" and treats "no" as "enabled".
- Both channel flags (`emailEnabled`, `mercureEnabled`) default to `true` on
  write; an upsert always writes a complete row for the categories included
  in the request body. Categories not included are left untouched.

### Enforcement Seam

`SendNotificationHandler` is the enforcement point (not `NotificationService`,
which is a thin pass-through to the handler). The order is deliberate and
preserved from the pre-existing persist-then-dispatch flow:

1. Validate the command and resolve/normalize channels (as before).
2. **Persist the notification row** via `NotificationRepositoryPort::save()`.
   This always happens — preferences suppress delivery, never persistence.
   The notification stays visible in `GET /api/notifications` regardless of
   what the preference check below decides.
3. Look up the user's preference for `NotificationType::category($type)` via
   `NotificationPreferenceRepositoryPort::findByUserIdAndCategory()` — only
   when the notification has a `recipientUserId` (email-only notifications
   have no user to key preferences on, so they always deliver).
4. For each channel about to be dispatched, `isChannelSuppressedByPreference()`
   checks the matching flag. A suppressed channel is skipped (not sent),
   marked `channelDelivery[channel] = false`, and logged at `info` level —
   the same `channelDelivery` map failed deliveries already use, just without
   treating it as an error.
5. If every requested channel ends up suppressed, the loop simply produces an
   all-`false` `channelDelivery` map and returns normally. This is a
   legitimate "user opted out" outcome, not a thrown exception — the same
   way an all-suppressed request from channel *failures* does not throw.

### Organization Scoping

Every notification may optionally carry an `organizationId` (see
[Persistence](#persistence)). The list, unread-count, and read-all endpoints
all accept an optional `organization` query filter:

- when provided, only notifications belonging to that organization are
  returned/counted/marked;
- when omitted, notifications across all organizations are included, together
  with account-level notifications (e.g. `user.email_verified`) and platform
  announcements that have no organization at all.

`organizationId` flows end-to-end: `SendNotificationRequest` (inbound
contract) → `Notification` aggregate → `NotificationRecord` → every read
result/output (`NotificationOutput`, `GetUserNotificationResult`,
`MarkNotificationAsReadResult`, `SendNotificationResult`, `SentNotification`).

### Senders populate `organizationId` (L1.6b)

As of L1.6b, every sender that constructs a `SendNotificationRequest` and
already holds an organization id in scope passes it through. This was a
follow-up to L1.6, which added the column/filter/unified-inbox scoping but
left every existing caller omitting the field (defaulting every row to
`null` and leaving the feature inert). The rule applied at each site:
thread the id only when the handler/service already possesses it (a command
field, an already-loaded aggregate/member, or an event payload) — never add
a repository lookup just to obtain one for a notification.

Senders that populate it: every Organization membership/invitation/plan
handler (`AddOrganizationMemberHandler`, `RemoveOrganizationMemberHandler`,
`RevokeOrganizationInvitationHandler`, `AcceptOrganizationInvitationHandler`,
`ChangeOrganizationPlanHandler`, `OrganizationInvitationNotifier` and its
two callers `InviteOrganizationMemberHandler`/
`ResendOrganizationInvitationHandler`), `ArchiveFacilityHandler`,
`PutUnderMaintenanceHandler`, `MaintenanceReminderNotifier`,
`InterventionNotificationService` (both the private per-member `send()` and
`mentioned()`), `InterventionRecurrenceNotifier`,
`MessagingNotificationService` (`mentioned()` and
`channelMessagePosted()`), and `OnboardingNotificationSubscriber`.

Notification types that remain deliberately org-less (do not "fix" these):

- `user.email_verified` (`UserEmailVerifiedEventHandler`) — an account-level
  event; the handler has no organization in scope, on purpose.
- `system.*` platform announcements — no organization owns a platform-wide
  message.
- `SendNotificationConsoleCommand` (`app:notification:send`) defaults to no
  organization; it now accepts an optional `--organization-id` operator flag
  for the rare case a manually-sent notification should be scoped, but omits
  it by default since a CLI invocation has no inherent organization context.

## Inter-Module Usage

Other modules should use:

- Inbound port: `Notification\Application\Port\Inbound\NotificationPort`
- Request contract: `Notification\Application\Contract\Notification\SendNotificationRequest`
- Channels enum: `Notification\Application\Contract\Notification\NotificationChannel`

Minimal example:

```php
$sent = $notificationPort->send(new SendNotificationRequest(
  type: 'organization.invitation',
  subject: 'Invitation to join Fireguard',
  body: '<p>Open invitation details.</p>',
  channels: [NotificationChannel::EMAIL, NotificationChannel::MERCURE],
  payload: ['organizationId' => '...'], // persisted
  deliveryPayload: [ // ephemeral, not persisted
    NotificationChannel::EMAIL->value => [
      'template' => 'notification/email/organization_invitation.html.twig',
      'context' => [
        'organizationName' => 'Fireguard HQ',
        'token' => '...',
        'expiresAt' => '2026-02-20T10:00:00+00:00',
      ],
    ],
  ],
  recipientUserId: '...',
  recipientEmail: 'member@example.com',
));
```

Returned contract: `SentNotification` includes `channelDelivery` (`array<string, bool>`)
to indicate success/failure per channel.

## Delivery Semantics

- Validation is strict before persistence:
  - at least one target (`recipientUserId` or `recipientEmail`),
  - `email` channel requires a deliverable address: when `recipientEmail` is
    absent it is resolved from `recipientUserId` through
    `RecipientDirectoryPort` (aliased to
    `User\Infrastructure\Adapter\Notification\UserRecipientDirectoryAdapter`);
    if still unresolvable, a multi-channel request drops the email channel
    (logged, other channels deliver) while an email-only request throws,
  - `mercure` channel requires `recipientUserId`,
  - non-empty `type` and `subject`.
- Notification is persisted first, then channels are executed in best-effort mode.
- Channel failure does not abort notification creation:
  - failure is logged,
  - `channelDelivery[channel] = false`.
- `deliveryPayload` is channel-specific runtime data and is never persisted.

Channel details:

- Email channel (`EmailNotificationChannelAdapter`):
  - uses `MailerPort`,
  - renders a Twig template (default: `notification/email/default.html.twig`),
  - optional custom template: `deliveryPayload['email']['template']`,
  - optional template vars: `deliveryPayload['email']['context']`,
  - `deliveryPayload['email']['body']` is still supported as default-template body override.
- Mercure channel (`MercureNotificationChannelAdapter`):
  - publishes private updates to topic: `/{topicPrefix}/{userId}/notifications`
    (default prefix: `/users`).

## Mercure Real-Time Subscription

To receive real-time notification updates, clients must:

1. Call `GET /api/notifications/subscription` (Bearer token required).
2. Receive `{ "token": "<subscriber JWT>", "topic": "/users/{id}/notifications" }`.
3. Open an SSE connection to the Mercure hub public URL:
   ```
   EventSource(MERCURE_PUBLIC_URL + '?topic=' + encodeURIComponent(topic), {
     headers: { Authorization: 'Bearer ' + token }
   })
   ```
   (or pass the token via the `mercureAuthorization` cookie if the client supports it).

The subscriber JWT is scoped to the user's own topic only (subscribe-only, no publish).
It is signed with the same `MERCURE_JWT_SECRET` used by the hub.

## Visibility Model

User read APIs only return notifications matched by `recipientUserId`.

Implication:

- Email-only notifications (without `recipientUserId`) are persisted for audit,
  but are not returned by `/api/notifications`.
- `GET /api/notifications` also masks older read notifications from low-value
  categories (`user`, `facility`, `equipment`) after 30 days. This is a list
  visibility rule only: notifications are not deleted by this behavior, and
  `GET /api/notifications/{id}` can still return a notification that no longer
  appears in the default list. The list's `totalItems` reflects the same
  masking (it is the total of what the list would return across every page,
  not a raw unmasked row count). `GET /api/notifications/unread-count` and
  `PATCH /api/notifications/read-all` are unaffected by this masking — it
  only ever hides already-read notifications, and both of those endpoints
  only ever look at/act on unread ones.

## Pagination

`GET /api/notifications` is a standard API Platform paginated collection
(`page` / `itemsPerPage`, default 20 items per page, client-adjustable). The
`ListUserNotificationsHandler` asks the repository for both the current page
(`findByUserId`, with `limit`/`offset`) and the total matching count
(`countByUserId`, same filters, no pagination) so `ListNotificationsProvider`
can return a `TraversablePaginator` with an accurate `totalItems`.

## Unified Inbox Seam (L1.8a)

`GET /api/inbox` merges several kinds of "things needing the user's
attention" (notifications today; @-mentions, direct messages and thread
replies from Messaging as a later, separate lot) into one
reverse-chronological, cursor-paginated feed. It is a **tagged-iterator
aggregator**, a direct clone of the `messaging.subject_resolver` seam
(`Messaging\Application\Service\MessagingSubjectResolverRegistry` /
`Messaging\Application\Port\Outbound\MessagingSubjectResolverPort`) — read
those two files to see the shape this mirrors. This section is written so a
Messaging adapter can be built against the seam without reading
Notification's code.

### The seam, precisely

- **Tag**: `inbox.source_provider`. Any service tagged with it is picked up
  automatically by the aggregator; adding a source requires zero edits to
  Notification.
- **Port**: `Notification\Application\Port\Outbound\InboxSourceProviderPort`
  — two methods:
  - `sourceKey(): string` — a short, stable key (e.g. `notification`,
    `messaging.mention`) used both to label items and as the aggregator's
    tie-breaker.
  - `fetch(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit): list<InboxItem>`
    — returns this source's most recent items for one user, bounded to at
    most `$limit` (a hard query cap, never a whole-table load), optionally
    scoped to one organization, optionally restricted to items with
    `occurredAt` strictly before the cursor. Must never throw for "nothing
    to return" (empty array instead) — the aggregator wraps every call
    defensively regardless, but a well-behaved provider should not rely on
    that net.
- **Contract DTO**: `Notification\Application\Contract\Inbox\InboxItem`
  (plain, framework-free — never a provider module's Domain object):
  `sourceKey`, `id`, `kind` (e.g. `notification`, `mention`,
  `direct_message`, `thread_reply`), `title`, `snippet` (nullable),
  `occurredAt` (`DateTimeImmutable`), `isRead`, `organizationId` (nullable),
  `targetType` + `targetId` (what the client should navigate to).
- **Aggregator**: `Notification\Application\Service\InboxAggregator`,
  constructor-injected `iterable $providers` wired as
  `!tagged_iterator inbox.source_provider` in `config/modules/notification.yaml`.
  `aggregate(userId, organizationId, before, limit): InboxAggregationResult`
  calls every provider with the same `$limit` (bounded merge: each source is
  asked for at most `$limit` items, never a whole source), merge-sorts the
  combined results, and truncates to `$limit`.
- **Use case**: `Application/UseCase/Query/Inbox/ListInboxItems` (query bus,
  like every other read) sits between the `GetInboxProvider` and the
  aggregator, clamping the requested page size (1–50) and turning the
  aggregation result into `nextCursor`/`hasMore`.

### Cursor semantics

Pagination is **cursor-based only** (`?before=<ISO-8601 instant>`) —
**never offset**. Offset over a merged heterogeneous feed is unstable by
construction: concurrent writes to any source shift the window and produce
duplicated or skipped rows across pages. This is stated in the endpoint's
OpenAPI `description` precisely so nobody "fixes" it into offset pagination
later.

- Omit `before` for the first page.
- Each response includes `nextCursor` (the `occurredAt` of the last
  returned item, as ISO-8601) and `hasMore` (boolean). Send the previous
  `nextCursor` back as `before` to fetch the next page; stop once `hasMore`
  is `false`. `nextCursor` is `null` whenever `hasMore` is `false`.
- `hasMore` is `true` when the pre-truncation merged result held more than
  `limit` items, OR when any single source itself returned a full page
  (`>= limit`) — the latter case covers a single-source page that exactly
  fills `limit` but may still have more beyond what was fetched. This is a
  conservative heuristic (may occasionally return one empty extra page); it
  never under-reports "there is more".

### Ordering and the tie-breaker

Items are sorted by `occurredAt` descending (most recent first). Because
`occurredAt` alone can tie (e.g. two items from different sources in the
same second, or a source bulk-writing several rows at once), the
tie-breaker is deterministic and stable:

1. `occurredAt` descending (primary)
2. `sourceKey` ascending (secondary)
3. `id` ascending (tertiary)

This never depends on provider registration order or PHP array insertion
order, so pagination stays stable across repeated requests as long as the
underlying data does not change.

### Failure isolation

`InboxAggregator` wraps every `fetch()` call in a try/catch: a throwing (or
otherwise misbehaving) provider degrades to "that source contributed
nothing" for the current page — it never fails the whole `GET /api/inbox`
request. The failure is logged at `error` level via `LoggerPort`
(`sourceKey` + exception message in the context), so degradation stays
visible in logs rather than silent.

### Building the Messaging adapter (not yet wired)

**The Messaging source (@-mentions, direct messages, thread replies) is NOT
wired yet.** This lot only ships the seam and the Notification-backed
provider. To add it, following the exact same shape as
`messaging.subject_resolver` adapters (see `Facility`, `Equipment`,
`Intervention`, `Inspection`'s `Infrastructure/Adapter/Messaging/`):

1. Host the adapter in the **provider module** (Messaging), under
   `Messaging\Infrastructure\Adapter\Notification\` (e.g.
   `MessagingInboxSourceProviderAdapter`), implementing
   `Notification\Application\Port\Outbound\InboxSourceProviderPort`.
2. Register it in **Messaging's own** `config/modules/messaging.yaml` with
   `tags: ['inbox.source_provider']` — do not touch
   `config/modules/notification.yaml`.
3. `fetch()` must resolve `$userId` to the conversations/channels the user
   participates in, filter by `$organizationId` when provided, apply
   `$before` as a strict "before this instant" cursor on the source's own
   ordering column, and cap results to `$limit` (bounded merge — never load
   a whole conversation history).
4. Map each mention/direct-message/thread-reply to an `InboxItem` with a
   `kind` such as `mention`, `direct_message`, or `thread_reply`,
   `targetType: 'conversation'` (or similar) and `targetId` set to whatever
   the client needs to open the right conversation/message.
5. Nothing in Notification changes: `InboxAggregator` picks up the new
   tagged service automatically via `!tagged_iterator inbox.source_provider`.

## Architecture

- Presentation: Api Platform resources, providers, processor, DTO output.
- Application:
  - Commands: `SendNotification`, `MarkNotificationAsRead`, `MarkAllNotificationsAsRead`, `UpdateNotificationPreferences`
  - Queries: `ListUserNotifications`, `GetUserNotification`, `GetUnreadNotificationsCount`, `GetNotificationPreferences`, `ListInboxItems`
  - Contracts and inbound service (`NotificationService`)
  - Unified inbox seam: `InboxAggregator`, `InboxSourceProviderPort`, `InboxItem` contract (see above)
- Domain: `Notification` aggregate + `NotificationId`; `NotificationPreference` model.
- Infrastructure: Doctrine repository/record/mapper + channel adapters (one
  repository/mapper pair per aggregate: `NotificationRepository` and
  `NotificationPreferenceRepository`); `NotificationInboxSourceProviderAdapter`
  (`Infrastructure/Adapter/Inbox/`) is the one concrete `inbox.source_provider`
  this module ships, reusing `NotificationRepositoryPort::findByUserId()`.

## Persistence

- Table: `notifications` (main database).
- Doctrine mapping: `src/Notification/Infrastructure/Persistence/Doctrine/Record`.
- Migration: `migrations/main/Version20260211153000.php` (base table),
  `migrations/main/Version20260718115756.php` (adds the nullable
  `organization_id` column and the
  `idx_notifications_user_org_created (recipient_user_id, organization_id, created_at)`
  index, and creates `notification_preferences`).
- `organization_id` is nullable by design: account-level notifications
  (`user.email_verified`) and platform announcements legitimately have none,
  and every row written before the column existed has none. Filtering by
  `organization` is therefore always optional/additive, never a required
  scope.
- Repository: `Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository`.
  It exposes `findByUserId`/`countByUserId` (paginated list + matching
  total), `countUnreadByUserId` (unread badge), and `markAllAsReadForUser`
  (single bulk `UPDATE`, not a load-all-then-save loop). `findByUserId`
  also accepts an optional `before` cursor (`n.createdAt < :before`, applied
  in the shared `filteredQueryBuilder`) — added for the unified inbox seam
  (`NotificationInboxSourceProviderAdapter`) so it can page by cursor instead
  of offset; the paginated list endpoint does not pass it and is unaffected.
- Table: `notification_preferences` (main database), composite PK
  (`user_id`, `category`), `email_enabled`/`mercure_enabled` (both default
  `true`), `updated_at`. No foreign key on `user_id`: notifications live on
  `main` while `users` lives on `auth`, and a cross-database constraint is
  not expressible (same reasoning as `notifications.recipient_user_id`).
  Repository: `NotificationPreferenceRepository`, exposing `findByUserId`
  (every customization for a user), `findByUserIdAndCategory` (the
  enforcement-seam lookup), and `saveMany` (batch upsert, single flush).

## Configuration

- Service wiring: `config/modules/notification.yaml`
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`
- Mercure bundle config: `config/packages/mercure.yaml`
- Required Mercure env vars:
  - `MERCURE_URL`
  - `MERCURE_PUBLIC_URL`
  - `MERCURE_JWT_SECRET`
- Email delivery relies on shared mailer configuration (`MAILER_DSN`, sender config in Shared mail adapter).

## Error Mapping

- API:
  - `401/403` when unauthenticated or forbidden by resource security.
  - `404` when notification does not exist or does not belong to the user.
- Send use case:
  - throws `InvalidArgumentException` on invalid input.
  - channel errors are logged and reported in `channelDelivery` (no hard failure).

## Testing

- Unit tests: `tests/Unit/Notification`
- Included coverage:
  - send flow + delivery status behavior, including preference suppression
    (no rows → everything delivered, unknown category → delivered, email
    disabled → row still persisted but email skipped, every channel
    suppressed → no throw),
  - providers (`list`, `get`, `unread-count`, `preferences`) including
    nested not-found mapping,
  - mark-as-read and mark-all-as-read handlers/processor,
  - `GetNotificationPreferences`/`UpdateNotificationPreferences`
    handlers/provider/processor, including duplicate-category
    de-duplication (last entry wins) on update,
  - list pagination + `total`, and organization scoping,
  - unified inbox seam: `InboxAggregatorTest` (merge ordering across
    sources, the `occurredAt` → `sourceKey` → `id` tie-breaker, cursor/limit
    forwarded unchanged to every provider, truncation to page size,
    `hasMore` derivation, and a throwing provider degrading to an empty
    contribution while the surviving source still returns its items — using
    a fake `InboxSourceProviderPort` implementation rather than a mock),
    `ListInboxItemsHandlerTest` (limit clamping, `nextCursor`/`hasMore`
    derivation, composed with a real `InboxAggregator` since it is `final`),
    `NotificationInboxSourceProviderAdapterTest` (forwards cursor/org/limit
    to `NotificationRepositoryPort::findByUserId()`, maps `Notification` →
    `InboxItem`), `GetInboxProviderTest` (auth guard, filter parsing,
    malformed-cursor → 400).
- Integration tests: `tests/Integration/Notification/Infrastructure/Persistence/Doctrine/Repository/NotificationPreferenceRepositoryIntegrationTest.php`
  exercises the composite-key (`user_id`, `category`) upsert against a real
  connection (insert-then-update in place, no duplicate row, per-user
  isolation) — a mocked QueryBuilder would not catch a broken composite
  `find()`/`persist()` shape. `NotificationRepositoryIntegrationTest` also
  covers the `before` cursor (`n.createdAt < :before`) added for the inbox
  seam, against a real connection.
- Functional API tests: `tests/Functional/Api/NotificationApiTest.php` (route
  contract/auth checks for `unread-count`, `read-all`, and `preferences` —
  including the `/preferences` vs `/{id}` route-ordering regression guard)
  and `tests/E2E/NotificationFlowTest.php` (full authenticated flow:
  pagination, organization filter, unread count, and read-all leaving
  another user's notifications untouched).
