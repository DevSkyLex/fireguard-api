# Webhook Module

**Reading guide:** [Documentation index](../../docs/README.md) · [Related guide](../../docs/guides/async-processing.md).

## Overview

Webhook lets an organization register outbound HTTP subscriptions that
receive a **curated, stable public contract** of significant events —
equipment lifecycle transitions, inspection/non-conformity events,
intervention publication, maintenance campaign generation, and facility
archival/restoration — as signed, asynchronous, retried POST requests.

Main goals:

- Turn a small, explicit allowlist of internal domain events into a stable
  public event contract, decoupled from internal refactors.
- Deliver asynchronously (never in the triggering request's thread), with
  retry/backoff and a durable per-attempt delivery log.
- Sign every delivery (HMAC-SHA256) so a consumer can verify authenticity
  and detect replay.
- Harden the target URL against SSRF (private/loopback/reserved addresses,
  cloud metadata endpoints) both at subscription time and at send time.

## API Endpoints

| Method | Path                                                                                         | Description                                                       | Permission                     |
| ------ | -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- | ------------------------------ |
| POST   | `/api/organizations/{organizationId}/webhooks`                                               | Create a subscription; returns the plaintext signing secret once  | `organization.webhooks.manage` |
| GET    | `/api/organizations/{organizationId}/webhooks`                                               | List an organization's subscriptions                              | `organization.webhooks.read`   |
| GET    | `/api/organizations/{organizationId}/webhooks/{webhookId}`                                   | Get a subscription                                                | `organization.webhooks.read`   |
| PATCH  | `/api/organizations/{organizationId}/webhooks/{webhookId}`                                   | Partially update `url`/`eventTypes`/`isActive`/`description`      | `organization.webhooks.manage` |
| DELETE | `/api/organizations/{organizationId}/webhooks/{webhookId}`                                   | Delete a subscription and its delivery log                        | `organization.webhooks.manage` |
| POST   | `/api/organizations/{organizationId}/webhooks/{webhookId}/rotate-secret`                     | Generate a new signing secret; returns it once                    | `organization.webhooks.manage` |
| POST   | `/api/organizations/{organizationId}/webhooks/{webhookId}/ping`                              | Enqueue a synthetic `webhook.ping` test delivery                  | `organization.webhooks.manage` |
| GET    | `/api/organizations/{organizationId}/webhooks/{webhookId}/deliveries`                        | Delivery log (filter: `status`)                                   | `organization.webhooks.read`   |
| POST   | `/api/organizations/{organizationId}/webhooks/{webhookId}/deliveries/{deliveryId}/redeliver` | Re-enqueue a delivery (any status, including terminally `failed`) | `organization.webhooks.manage` |
| GET    | `/api/webhooks/event-types`                                                                  | Reference catalog of subscribable event types                     | `ROLE_USER`                    |

`/api/webhooks/event-types` has no first-party web consumer today — it is retained
deliberately as a public-API discovery affordance for external integrators wiring up
webhook subscriptions, unlike the other unconsumed reference catalogs removed
2026-08-20 elsewhere in the codebase. This is a recorded decision, not an oversight.

Every operation requires `ROLE_USER` at the resource level; the
finer-grained permission checks above are self-enforced in the application
layer by each handler through `OrganizationAuthorizationPort`, mirroring
Maintenance/Import.

**Deviation from the original lot brief:** the brief's `sharedFilesModified`
sketch suggested a flat `/api/webhooks/**` route family (mirroring Import,
which is org-scoped via a query filter, not a path segment) and routing
`RedeliverWebhookCommand` onto the async `webhook` transport. This module
instead nests every subscription/delivery route under
`/organizations/{organizationId}/webhooks/**`, mirroring
`TeamResource`/`OrganizationRoleResource` (path-based org scoping is the
majority convention for org-owned resources), and keeps
`RedeliverWebhookCommand` **synchronous** (dispatched via `CommandBusPort`
from the API processor) — only the freshly re-armed delivery's
`DeliverWebhookCommand` goes on the async `webhook` transport. Routing
`RedeliverWebhookCommand` itself onto `webhook` would break the endpoint's
synchronous JSON response: `MessengerCommandBusAdapter::dispatch()` requires
a `HandledStamp`, which an async-routed dispatch never produces (see
`Import\Infrastructure\Adapter\Messenger\MessengerImportJobQueueAdapter`'s
docblock for the precedent this avoids replicating).

## Curated event allowlist

`Application\Contract\Event\WebhookEventCatalog` holds the exact internal
dispatcher event names (`<module>.<snake_case_class>`, per
`Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter`),
cross-checked against the subscribers in `Audit\Infrastructure\EventSubscriber\`,
which already subscribe to every one of them:

| Public type (`WebhookEventType`)           | Internal dispatched event name                                                   |
| ------------------------------------------ | -------------------------------------------------------------------------------- |
| `equipment.commissioned`                   | `equipment.equipment_commissioned_event`                                         |
| `equipment.decommissioned`                 | `equipment.equipment_decommissioned_event`                                       |
| `equipment.under_maintenance`              | `equipment.equipment_put_under_maintenance_event`                                |
| `equipment.returned_to_stock`              | `equipment.equipment_returned_to_stock_event`                                    |
| `inspection.submitted`                     | `inspection.inspection_submitted_event`                                          |
| `inspection.closed`                        | `inspection.inspection_closed_event`                                             |
| `inspection.non_conformity_recorded`       | `inspection.non_conformity_recorded_event`                                       |
| `inspection.non_conformity_status_changed` | `inspection.non_conformity_status_changed_event`                                 |
| `intervention.published`                   | `intervention.intervention_published_event`                                      |
| `maintenance.campaign_generated`           | `maintenance.maintenance_campaign_generated_event`                               |
| `facility.created`                         | `facility.facility_created_event`                                                |
| `facility.archived`                        | `facility.facility_archived_event`                                               |
| `facility.restored`                        | `facility.facility_restored_event`                                               |
| `facility.updated`                         | `facility.facility_updated_event`                                                |
| `webhook.ping`                             | _(never dispatched from a real event — reserved for the test-delivery endpoint)_ |

Excluded by policy: every auth/oauth/otp/session event (security-internal),
per-message Messaging events (volume/PII), and Audit's own events. Renaming
a source domain event requires updating both `WebhookEventCatalog` and
`WebhookEventSubscriber` — do not rename without checking both.

## Flows

### Dispatch (source event — enqueue only)

Event handling enqueues delivery work and returns to the producer. The request thread does not perform outbound webhook HTTP delivery.

```mermaid
---
config:
  sequence:
    wrap: true
---
sequenceDiagram
  participant Src as Source handler (e.g. RecordNonConformity)
  participant Disp as EventDispatcherPort
  participant Sub as WebhookEventSubscriber
  participant Bus as MessageBusInterface (webhook transport)
  Src->>Disp: dispatch(NonConformityRecordedEvent)
  Disp->>Sub: onNonConformityRecorded(event)
  Note over Sub: Durable delivery failures propagate for retry;<br/>legacy synchronous delivery remains best-effort
  Sub->>Bus: dispatch(DispatchWebhookEventCommand)
```

`WebhookEventSubscriber` subscribes to curated event names with one typed handler
per event and dispatches directly onto the raw Symfony message bus. Durable source
events reuse their outbox identity and propagate enqueue failures to Messenger;
legacy synchronous calls log failures without invalidating their source request.
`CommandBusPort` expects a `HandledStamp` an async-routed dispatch never produces.

### Fan-out + delivery (async, `webhook` transport)

The dedicated worker resolves eligible subscriptions and sends signed payloads. Failure and replay use the webhook transport's retry and destination contract.

```mermaid
---
config:
  sequence:
    wrap: true
---
sequenceDiagram
  participant Worker as messenger:consume webhook
  participant DispatchH as DispatchWebhookEventHandler
  participant Repo as WebhookDeliveryRepositoryPort
  participant DeliverH as DeliverWebhookHandler
  participant Http as WebhookHttpClientPort
  Worker->>DispatchH: DispatchWebhookEventCommand
  DispatchH->>DispatchH: find active subscriptions matching org+eventType
  loop each matching subscription
    DispatchH->>Repo: reserve(deliveryId, subscriptionId, eventId, payload)
    Note over Repo: raw-DBAL unique-insert on (subscription_id, event_id) —<br/>duplicate is a routine, expected outcome, not an exception
    DispatchH->>Worker: enqueue DeliverWebhookCommand(deliveryId)
  end
  Worker->>DeliverH: DeliverWebhookCommand
  DeliverH->>Repo: claim(deliveryId, generation, attemptToken, 90-second lease)
  DeliverH->>DeliverH: decrypt secret, sign, build headers
  DeliverH->>Http: post(url, headers, body, timeout)
  alt 2xx
    DeliverH->>Repo: finalize only owned generation/token (status=delivered)
  else non-2xx / transport error, attempts < 5
    DeliverH->>Repo: finalize pending + reserve/enqueue durable delayed wake atomically
  else attempts >= 5
    DeliverH->>Repo: finalize only owned generation/token (status=failed)
  end
```

`PingWebhookSubscriptionHandler` and `RedeliverWebhookHandler` join this
same pipeline directly (reserve/reopen + enqueue `DeliverWebhookCommand`),
bypassing the fan-out since they target exactly one subscription.

Reservation and enqueue share the `main` transaction for fan-out and ping.
Manual redelivery atomically increments the generation, clears the prior lease,
resets both budgets and enqueues that generation. An old command cannot acquire
or finalize a newer generation. Historical PHP-serialized commands lacking a
generation deserialize as generation **0**, never the current generation.

Delivery claims commit before HTTP; running inside an outer main transaction is
refused. HTTP starts only after its attempt count is durably incremented. A worker
crash therefore consumes the attempt it may have sent. Finalization checks the
generation, token and unexpired lease; stale responses have no effect.

Five HTTP calls per generation and five Messenger technical retries are separate
budgets. HTTP failures reserve their own durable delayed wakes (1, 3, 9, 27 seconds)
and return normally. Unexpected technical exceptions propagate to Messenger;
their persisted budget prevents periodic recovery from resetting exhaustion.
Exhausted technical generations are quarantined until manual redelivery.
Reserving a new wake transfers technical retry-cycle ownership once the prior
lease is absent or expired. Finalization also hands ownership to an already
reserved busy wake. A late failure of the preceding envelope, including a
historical command without a token, cannot quarantine the new wake. An active
lease retains its command's technical cycle; an expired attempt cannot release
or rewrite a wake reserved by recovery.
Claims verify this cycle identity before acquiring a lease. An older envelope
cannot reacquire the cycle when its replacement wake becomes due. Historical
reservations without a command identity are adopted only by their exact wake;
tokenless historical commands retain their own technical retries.

Busy leases and future retry times retain a delayed wake without consuming an
HTTP attempt. A one-minute sweep recovers expired leases and stale wake messages;
atomic reservations prevent competing sweep workers from enqueueing the same
wake. Delivered, failed and quarantined generations are excluded. A crash on the
fifth HTTP attempt becomes terminal once its lease expires.

## Signature & replay protection

- `timestamp` = unix seconds at send time.
- `signedPayload` = `"{timestamp}.{rawJsonBody}"`.
- `signature` = `hash_hmac('sha256', signedPayload, secretPlaintext)`.

Headers sent with every delivery:

| Header                          | Value                                                                                 |
| ------------------------------- | ------------------------------------------------------------------------------------- |
| `Content-Type`                  | `application/json`                                                                    |
| `User-Agent`                    | `Fireguard-Webhooks/1.0`                                                              |
| `X-Fireguard-Webhook-Id`        | the delivery UUID — the consumer-side idempotency key (retries/redeliveries reuse it) |
| `X-Fireguard-Webhook-Event`     | the public event type (e.g. `intervention.published`)                                 |
| `X-Fireguard-Webhook-Timestamp` | the signed unix timestamp                                                             |
| `X-Fireguard-Webhook-Signature` | `sha256=<hex hmac>`                                                                   |

A consumer should reject a delivery if `|now - timestamp| > 300s` (replay
window) and recompute the HMAC over the exact raw request body before
trusting it.

## Payload envelope

```json
{
  "id": "018f...-delivery-uuid",
  "type": "intervention.published",
  "created": "2026-07-18T09:00:00+00:00",
  "organizationId": "018f...-org-uuid",
  "data": { "interventionId": "...", "publicationId": "..." }
}
```

`data` is built by a dedicated block inline in `WebhookEventSubscriber`
(one per curated event, following the Audit subscribers' inline metadata
blocks) that reads the TYPED domain event and emits only stable, PII-free
public fields — no emails, no IP addresses, no internal identifiers beyond
the resource IDs already visible through the REST API.

## Secret storage (reversible — deliberate)

A webhook signing secret is stored **encrypted, not hashed** — the opposite
of `Shared\Domain\ValueObject\HashedSecret` used for OAuth client secrets
(one-way, verify-only). HMAC signing must reproduce the plaintext on every
delivery attempt, so `OpensslWebhookSecretCipherAdapter` (AES-256-GCM,
`WEBHOOK_ENCRYPTION_KEY`) encrypts it at rest. A reviewer must **not**
"harden" this into a one-way hash.

- Generated as `whsec_` + `bin2hex(random_bytes(24))` (mirrors Stripe's
  `STRIPE_WEBHOOK_SECRET` convention already used in this codebase).
- Returned in **plaintext exactly once**: at create time and at
  rotate-secret time. Every other read returns `WebhookSubscriptionOutput`,
  which never carries it.
- `WEBHOOK_ENCRYPTION_KEY` (base64-encoded 32 raw bytes) must never be
  logged. **Key-rotation runbook:** rotating this key invalidates every
  previously stored secret (decryption fails); a rotation requires
  re-encrypting every `webhook_subscriptions.secret_ciphertext` row with
  the new key (decrypt-with-old, encrypt-with-new) in the same maintenance
  window, or accepting that every existing subscriber must be re-created.

## SSRF hardening

`Domain\Service\WebhookUrlPolicy` (pure, I/O-free) is shared by three call
sites:

1. `Presentation\Api\Validator\ValidWebhookUrl\ValidWebhookUrlValidator` —
   input-time UX validation (400/422 on a bad URL).
2. `CreateWebhookSubscriptionHandler` / `UpdateWebhookSubscriptionHandler` —
   the **authoritative** business-rule gate; never trust the API layer
   alone.
3. `Infrastructure\Adapter\Http\SymfonyHttpWebhookClientAdapter` — **send-time
   re-validation** of scheme and literal addresses, including bracketed IPv6.
   The required `NoPrivateNetworkHttpClient` pins validated DNS results and
   checks the actual connected address. `max_redirects: 0` prevents retargeting.

Rules: `https://` required unless `WEBHOOK_ALLOW_INSECURE_URLS=true` (dev
only — never in prod); the host, once resolved to a literal IP, is rejected
if it falls in a private (RFC 1918/4193), loopback, link-local (which
covers the `169.254.169.254` cloud metadata endpoint), or otherwise
reserved range (`filter_var(..., FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE
| FILTER_FLAG_NO_RES_RANGE)`).

Idle and total request durations are bounded to 1–60 seconds. The guarded client
resets its DNS cache between worker messages. Delivery errors expose safe reasons
(invalid/disallowed URL, timeout, unreachable/disallowed destination), never raw
transport messages containing internal hostnames or URL credentials.

## API tokens (documented decision — no PAT table)

Machine-to-machine REST access reuses the **existing OAuth2
client-credentials grant** (`POST /oauth2/token`, `grant_type=client_credentials`,
already supported by `league/oauth2-server`'s `ClientCredentialsGrant`) —
no new Personal Access Token table was added in this lot. Justification:

- Webhook subscription management and delivery-log reads are human-admin
  tasks, driven by the existing user session + org RBAC
  (`organization.webhooks.{read,manage}`); they need no machine token.
- A webhook **consumer** only needs the HMAC secret to verify inbound
  deliveries — it is never called back by Fireguard for anything else, so
  it needs no API token at all.
- OAuth2 client-credentials tokens are global SSO clients scoped to the
  **auth** database and carry OAuth scopes, not organization RBAC —
  bridging client → organization permission would require new auth-DB
  schema, a new authenticator, and `security.yaml` firewall changes, well
  beyond this lot's need.

If org-scoped programmatic management of webhooks is later required, the
minimal recommended follow-up is an org-scoped service-account token bound
to `{organizationId, permission scopes}` — captured here as a documented
future item, not built now.

## Rate / volume limits

Default (minimal blast radius): `CreateWebhookSubscriptionHandler` rejects
past 20 active subscriptions per organization
(`WebhookValidationException`). No per-org delivery-rate limiter is wired
in this lot; the dedicated `webhook` Messenger transport's own
`retry_strategy` bounds the blast radius of a single unreachable endpoint.
A documented, deferred alternative for monetization: promote the cap to
`OrganizationQuotaCatalog` (a `webhook_subscriptions` resource) enforced via
the existing `OrganizationQuotaLockPort`/409 pattern, gating pro/max plans.

## Architecture

- **Domain** (`src/Webhook/Domain`): `WebhookSubscription` (aggregate:
  create/reconstitute/update/rotateSecret/subscribesTo), `WebhookDelivery`
  (lifecycle aggregate: pending → delivered/failed, mirrors
  `Import\Domain\Model\ImportJob\ImportJob`'s state-machine style),
  `WebhookDeliveryStatus`, `WebhookSubscriptionId`/`WebhookDeliveryId`,
  `WebhookUrlPolicy` (SSRF policy), domain events, exceptions.
- **Application** (`src/Webhook/Application`): `WebhookEventCatalog` /
  `WebhookEventType` / `WebhookPayloadEnvelope` (Contract), outbound ports,
  use cases (subscription CRUD + rotate-secret + ping, delivery
  dispatch/deliver/redeliver, subscription/delivery queries).
- **Infrastructure** (`src/Webhook/Infrastructure`): `WebhookEventSubscriber`,
  `MessengerWebhookDeliveryQueueAdapter`, `SymfonyHttpWebhookClientAdapter`,
  `OpensslWebhookSecretCipherAdapter`, Doctrine Record/Mapper/Repository
  (main entity manager).
- **Presentation** (`src/Webhook/Presentation`): `WebhookSubscriptionResource`,
  `WebhookDeliveryResource`, `WebhookEventTypeResource` (reference catalog),
  processors, providers, Input/Output DTOs, `ValidWebhookUrl` validator,
  `WebhookExceptionMapperTrait`.

### Ports & adapters (`config/modules/webhook.yaml`)

| Port                                | Adapter                                |
| ----------------------------------- | -------------------------------------- |
| `WebhookSubscriptionRepositoryPort` | `WebhookSubscriptionRepository`        |
| `WebhookDeliveryRepositoryPort`     | `WebhookDeliveryRepository`            |
| `WebhookDeliveryQueuePort`          | `MessengerWebhookDeliveryQueueAdapter` |
| `WebhookSecretCipherPort`           | `OpensslWebhookSecretCipherAdapter`    |
| `WebhookHttpClientPort`             | `SymfonyHttpWebhookClientAdapter`      |

Cross-module: every command/query handler self-enforces
`organization.webhooks.{read,manage}` via
`Organization\Application\Port\Inbound\OrganizationAuthorizationPort`
(the Intervention/Maintenance/Import convention).

## Permissions

`organization.webhooks.read` / `organization.webhooks.manage`
(`Organization\Domain\Catalog\OrganizationPermissionCatalog`). **Not**
added to `OrganizationSystemRoleCatalog`'s `member` role — webhooks are an
admin/integration capability, granted only via a custom organization role
or the `organization.*` owner wildcard.

## Persistence

- Tables (**main** database): `webhook_subscriptions` (unique-per-id;
  index `(organization_id, is_active)`; FK `organization_id` →
  `organizations.id` `ON DELETE CASCADE`) and `webhook_deliveries` (unique
  `(subscription_id, event_id)` — the fan-out idempotency guard; index
  `(status, next_retry_at)`; index `(subscription_id, created_at)`; FK
  `subscription_id` → `webhook_subscriptions.id` `ON DELETE CASCADE`; FK
  `organization_id` → `organizations.id` `ON DELETE CASCADE`).
- Doctrine mapping: `src/Webhook/Infrastructure/Persistence/Doctrine/Record`
  (main entity manager). No ORM associations (plain string columns), FKs
  added directly in the migration — mirrors `Import\...\ImportJobRecord`.
- Migrations: `migrations/main/Version20260718063344.php` and the additive
  `Version20261011120000.php` for generation, attempt token, lease, wake reservation,
  technical-failure count and quarantine state, then `Version20261011120001.php`
  for retry-cycle ownership. Existing rows remain generation 0.

## Configuration

- Service wiring: `config/modules/webhook.yaml`.
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`.
- Messenger: `config/packages/messenger.yaml` — a dedicated `webhook`
  transport on **main**, `doctrine://main?queue_name=webhook&auto_setup=false`,
  with `redeliver_timeout: 120`, five technical retries and `main_failed`.
  The `main` sender and repository writes use the same connection. HTTP duration
  remains capped at 60 seconds, below the 90-second application lease.
  `when@test` uses an in-memory webhook transport for ordinary handler/API tests;
  atomicity integration tests instantiate the native Doctrine transport explicitly.
- Recovery: a stateful, lock-guarded `scheduler_webhook` schedule runs each minute.
  Run `messenger:consume webhook` and `messenger:consume scheduler_webhook` as
  separate supervised workers; preserve the existing `main_outbox` consumer.
- The old `failed` transport on auth remains readable for historical failed
  envelopes. New Webhook failures go to `main_failed`.
- Error mapping: Webhook resources use `WebhookExceptionSubscriber`; processors
  and providers propagate use-case failures. Existing status codes/messages remain.
- Env vars: `WEBHOOK_ENCRYPTION_KEY` (base64, 32 raw bytes),
  `WEBHOOK_ALLOW_INSECURE_URLS` (dev-only http:// toggle),
  `WEBHOOK_HTTP_TIMEOUT` (outbound request timeout, seconds).
- Run `php -d memory_limit=1G bin/console app:authz:sync-permissions --update-roles` after
  changing `OrganizationPermissionCatalog` — a no-op for these two
  permissions specifically, since `OrganizationPermissionCatalog` is a
  static catalog (no DB persistence/sync step exists for organization-scoped
  permissions, unlike the platform-level `Authorization` module the sync
  command actually targets); documented here to avoid re-investigating.

### Transport cutover and recovery runbook

1. Stop every producer of Webhook work, including the `main_outbox` worker that
   fans integration events out. Keep the old webhook worker available for draining.
2. Drain the old **auth** queue `webhook`, including delayed rows. Verify its row
   count is zero and wait for all outbound calls in progress to finish. Do not
   discard delayed or failed envelopes to accelerate the cutover. Stop the old
   webhook worker once the queue is empty and its active HTTP calls have finished.
3. Deploy the compatible code and apply both additive main migrations
   (`Version20261011120000` and `Version20261011120001`) with
   `--configuration=config/migrations/main.yaml`. Set up each main transport separately:

   ```sh
   for transport in webhook main_outbox main_failed; do
     php -d memory_limit=1G bin/console messenger:setup-transports "$transport" --env=prod --no-interaction || exit 1
   done
   ```

4. Start `scheduler_webhook` to recover previously committed pending reservations,
   then start the main `webhook` and `main_outbox` workers and restore producers.
5. Keep auth's historical failed transport readable. Replay its Webhook envelopes
   with the new decoder; missing generations always remain 0. Inspect `main_failed`
   and quarantined deliveries before explicitly requesting manual redelivery.

Monitor queue age, oldest pending delivery, expired leases and quarantined rows.
Run `php bin/console app:webhook:deliveries --env=prod --max-lease-age=180` for
aggregate counts and ages without exposing payloads, URLs or organization IDs.
The host's five-minute worker observation alerts on overdue leases, quarantined
or failed generations and recovery sweeps without success for five minutes.
Correct the cause before an explicit manual redelivery; generic Messenger replay
does not reopen a terminal generation.
Recovery never resets a terminal/exhausted generation. Do not roll back to a worker
that ignores generation fencing after this migration becomes active. An old HTTP
response can neither overwrite a newer result nor release its lease.

## Testing

- Unit: `tests/Unit/Webhook`
- Functional: `tests/Functional/Api/WebhookSubscriptionApiTest.php`
- PostgreSQL atomicity, fencing and recovery:
  `tests/Integration/Webhook/Infrastructure/Delivery/WebhookDeliveryAtomicityTest.php`
  uses independent connections and the real Doctrine transport, without relying
  on the in-memory override or DAMA's enclosing transaction.
- Run module tests: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Webhook`

## Error Codes

| Exception                                                                   | HTTP                     |
| --------------------------------------------------------------------------- | ------------------------ |
| `WebhookSubscriptionNotFoundException` / `WebhookDeliveryNotFoundException` | 404 Not Found            |
| `Organization\Application\Contract\Exception\OrganizationAccessDeniedException` | 403 Forbidden |
| `WebhookValidationException`                                                | 422 Unprocessable Entity |
| `InvalidArgumentException`                                                  | 400 Bad Request          |

## Organization management UI and safe delivery feedback

`/organizations/:organizationId/integrations/webhooks` consumes the existing subscription,
event catalog, test, secret rotation and delivery endpoints. `read` and `manage` remain
independent. Creation and rotation reveal the plaintext secret once; list/detail responses
never reveal it. Accepted tests and redeliveries are queued, not confirmed delivered.

Delivery output adds nullable `errorCode`: `webhook_timeout`, `webhook_http_error`,
`webhook_destination_disallowed`, `webhook_destination_unreachable`,
`webhook_subscription_missing` or the fallback `webhook_delivery_failed`. `lastError`
remains compatible as a safe public message. Old stored transport diagnostics are never
returned verbatim; successful deliveries expose neither field. No schema change is needed.
