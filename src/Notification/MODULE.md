# Notification Module

## Overview

Notification provides a generic internal notification system usable by other
modules through an inbound port. It supports multiple delivery channels and
exposes API endpoints for authenticated users to read their notifications.

Main goals:

- Persist notifications for traceability/audit.
- Deliver through configured channels (`email`, `mercure`).
- Expose user-facing read APIs (`list`, `get`, `mark as read`).

## API Endpoints

| Method | Path | Description | Handler |
| --- | --- | --- | --- |
| GET | `/api/notifications` | List notifications for authenticated user (`unreadOnly`, `limit`) | `ListNotificationsProvider` |
| GET | `/api/notifications/{id}` | Get one notification owned by authenticated user | `GetNotificationProvider` |
| PATCH | `/api/notifications/{id}/read` | Mark one notification as read (idempotent) | `MarkNotificationAsReadProcessor` |
| GET | `/api/notifications/subscription` | Get Mercure subscriber JWT and SSE topic for the authenticated user | `GetMercureSubscriptionProvider` |

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
  - `email` channel requires `recipientEmail`,
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

## Architecture

- Presentation: Api Platform resource, providers, processor, DTO output.
- Application:
  - Commands: `SendNotification`, `MarkNotificationAsRead`
  - Queries: `ListUserNotifications`, `GetUserNotification`
  - Contracts and inbound service (`NotificationService`)
- Domain: `Notification` aggregate + `NotificationId`.
- Infrastructure: Doctrine repository/record/mapper + channel adapters.

## Persistence

- Table: `notifications` (main database).
- Doctrine mapping: `src/Notification/Infrastructure/Persistence/Doctrine/Record`.
- Migration: `migrations/main/Version20260211153000.php`.
- Repository: `Notification\Infrastructure\Persistence\Doctrine\Repository\NotificationRepository`.

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
  - send flow + delivery status behavior,
  - providers (`list`, `get`) including nested not-found mapping,
  - mark-as-read handler and processor.
