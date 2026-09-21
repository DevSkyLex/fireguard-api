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
| GET | `/api/inbox` | Unified, cursor-paginated inbox feed merging every registered `inbox.source_provider` source (`organization`, `cursor`, `limit`; legacy `before`) | `GetInboxProvider` |
| GET | `/api/inbox/unread-count` | Unread item count summed across every registered `inbox.source_provider` source for the authenticated user (optional `organization` filter) | `GetInboxUnreadCountProvider` |

`/subscription`, `/unread-count`, `/read-all` and `/preferences` are declared
before the `/{id}` routes in `NotificationResource`; otherwise they would be
swallowed by the `{id}` placeholder. `GET /api/inbox` and `GET
/api/inbox/unread-count` deliberately live on their own top-level
`InboxResource` (`routePrefix: /inbox`), not nested under `/notifications`,
so they never have to compete with that ordering constraint (`InboxResource`
has no `/{id}` route at all).

### Unified inbox unread count

`GET /api/inbox/unread-count` sums Notification and Messaging contributions in the
requested scope. The notification-only endpoint remains available for its own lists.
Every source implements both `fetch` and `countUnread`; see the unified inbox contract
below for source limits and failure behavior.
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
3. Open an SSE connection to the Mercure hub public URL, passing the token in an
   `Authorization` header:
   ```
   fetch(MERCURE_PUBLIC_URL + '?topic=' + encodeURIComponent(topic), {
     headers: { Authorization: 'Bearer ' + token },
   })
   ```

The subscriber JWT is scoped to the user's own topic only (subscribe-only, no publish).
It is signed with the same `MERCURE_JWT_SECRET` used by the hub, and carries an `exp`
claim controlled by `mercure_subscriber_token_ttl` (override:
`MERCURE_SUBSCRIBER_TOKEN_TTL`, default 900s). The bundle's own token factory sets no
expiry, so the claim is applied explicitly by each subscription provider.

The hub validates `exp` only when a connection is opened; an established stream is not
dropped when its token expires. The TTL therefore bounds how long a leaked token can
open a *new* stream. Clients must re-request a subscription on every reconnect rather
than replaying the previous token.

### Why not the `mercureAuthorization` cookie

The hub accepts a `mercureAuthorization` cookie, and it would keep the credential out
of the URL just as well. It is deliberately **not** used here, for two reasons:

- **One cookie, many scopes.** Three endpoints mint narrowly-scoped subscriber tokens
  (`/users/{id}/notifications`, `/organizations/{o}/conversations/{c}`,
  `/organizations/{o}/assistant/threads/{t}`), and the notification and messaging
  streams run concurrently. A cookie is a single slot per origin, so the last
  subscription to be requested overwrites the others. Because all updates are published
  `private: true`, the mismatched stream still connects with HTTP 200 and simply
  receives nothing — a silent failure with no error for the client's retry layer to act
  on.
- **Cross-subdomain exposure.** The API (`api.`) and the hub (`mercure.`) are different
  hosts, so the cookie would need `Domain=.fireguard.valentin-fortin.pro`, sending it to
  every present and future subdomain. Every other cookie in this codebase is
  `__Host-`-prefixed and domain-less, which forbids exactly that.

An `Authorization` header preserves per-resource scoping and per-conversation
authorization; the hub allows it cross-origin (`Access-Control-Allow-Headers:
Authorization`) for any origin listed in `MERCURE_CORS_ORIGINS`.

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

## Unified inbox

`GET /api/inbox` merges account notifications and authorized Messaging mentions.
Messaging contributes only when `organization` is provided; notification scope is
optional. The adapter always applies ownership and current source permissions.

The published `InboxSourceProviderPort` is implemented by each source's infrastructure
adapter and registered in its own module with `inbox.source_provider`. Notification
never imports the contributing module. `fetch(userId, organizationId, before, limit,
cursor)` and `countUnread(userId, organizationId)` are the source contract.

`InboxItem` carries `sourceKey`, `id`, `kind`, title/snippet, `occurredAt`, `isRead`,
`organizationId`, `targetType`, `targetId`, and nullable `targetKind`. Identity is the
pair `(sourceKey, id)`; a mention opens its conversation using `targetKind` to select
channels or messages. Notification acknowledgement and conversation read markers
remain owned by their respective modules.

### Pagination and partial sources

- Ordering is `occurredAt DESC, sourceKey ASC, id ASC` for the aggregator AND each
  contributor's SQL predicate. `InboxCursor` preserves six fractional digits and binds
  UTC instants without DBAL's second-only datetime conversion.
- The first page omits `cursor`. Echo the response's opaque `nextPageCursor` as `cursor`
  for the next page. Tokens are versioned and strictly validated; malformed tokens or
  simultaneous `cursor` and `before` return 400. They convey position, never access.
- Each source is asked for `limit + 1` readable entries before merge/truncation. The
  public limit remains 1–50. `hasMore` reflects a real extra entry, not a full-page guess.
- A failed contributor is logged and remaining entries are returned with `complete:
  false` and no `nextPageCursor`. Retry that page before advancing; otherwise recovered
  source entries could be skipped. A partial empty response is not an empty inbox.
- Legacy `before` and `nextCursor` remain compatible but cannot disambiguate equal
  timestamps. Existing consumers may migrate independently; new consumers use `cursor`.

`GET /api/inbox/unread-count` sums source-owned counts independently of loaded pages.
Notification uses an exact SQL count. Messaging retains its documented bounded readable
window, so its contribution is a lower bound beyond 200 entries. A failing count source
is logged and contributes zero; this existing response does not assert completeness.

Sources must perform authorization and the cursor predicate before limiting. Messaging
refills bounded candidate batches when inaccessible conversations exhaust a batch.
Direct messages and replies exist in Messaging, but only mentions contribute inbox
entries; neither is a separate inbox source yet.
## Architecture

- Presentation: Api Platform resources, providers, processor, DTO output.
- Application:
  - Commands: `SendNotification`, `MarkNotificationAsRead`, `MarkAllNotificationsAsRead`, `UpdateNotificationPreferences`
  - Queries: `ListUserNotifications`, `GetUserNotification`, `GetUnreadNotificationsCount`, `GetNotificationPreferences`, `ListInboxItems`, `GetInboxUnreadCount`
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
    `InboxItem`, forwards user/org to `countUnreadByUserId()`),
    `GetInboxProviderTest` (auth guard, filter parsing, malformed-cursor →
    400), `GetInboxUnreadCountHandlerTest`/`GetInboxUnreadCountProviderTest`
    (sums every source via a real `InboxAggregator`, organization filter
    forwarding, auth guard).
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

## Error Codes

- API:
  - `401/403` when unauthenticated or forbidden by resource security.
  - `404` when notification does not exist or does not belong to the user.
- Send use case:
  - throws `InvalidArgumentException` on invalid input.
  - channel errors are logged and reported in `channelDelivery` (no hard failure).
