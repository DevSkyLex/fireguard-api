# Billing Module

## Overview

Billing turns the organization plans (`free` / `pro` / `max`) into real Stripe
subscriptions. It owns everything Stripe-specific; the `Organization` module
stays agnostic of Stripe. Payment runs through **hosted Stripe Checkout**, plan
and payment-method management through the **hosted Billing Portal**, and the
**Stripe webhook is the source of truth**: it is the webhook that actually
applies the `plan_id` to the organization (via a cross-module seam) and keeps the
local subscription projection in sync. The quota system is unchanged — it still
reads `organizations.plan_id` → `plan.limits`.

Persisted in the dedicated **main** database.

## Core capabilities

- Start a hosted Checkout session for a paid plan and cadence (monthly / yearly).
- Open the hosted Billing Portal (manage payment method, change plan, invoices).
- Cancel a subscription at period end, and resume a scheduled cancellation.
- Read the organization's current subscription state, self-sufficient for a
  "current plan" card (plan display name + display pricing included, no
  second call to the plan catalog needed).
- Read the organization's saved payment method (brand, last 4 digits, expiry)
  as a read model sourced live from Stripe.
- List the organization's recent invoices (in-app billing history).
- Expose the display pricing catalog joined by the frontend with the plan catalog.
- Reconcile Stripe `customer.subscription.*` webhooks into the local projection
  and the organization plan.

## API endpoints

| Method | Path | Description | Handler | Permission |
| --- | --- | --- | --- | --- |
| POST | `/api/organizations/{organizationId}/billing/checkout` | Start a hosted Checkout session for `{planKey, interval}`; returns the Stripe URL | `StartCheckoutProcessor` | `organization.settings.write` |
| POST | `/api/organizations/{organizationId}/billing/portal` | Open a hosted Billing Portal session; returns the Stripe URL | `StartPortalProcessor` | `organization.settings.write` |
| POST | `/api/organizations/{organizationId}/billing/cancel` | Schedule cancellation at period end; returns the refreshed subscription | `CancelSubscriptionProcessor` | `organization.settings.write` |
| POST | `/api/organizations/{organizationId}/billing/resume` | Clear a scheduled cancellation; returns the refreshed subscription | `ResumeSubscriptionProcessor` | `organization.settings.write` |
| GET | `/api/organizations/{organizationId}/billing/subscription` | Get the current subscription state (status, plan key/display name, display pricing, renewal, scheduled cancel) — self-sufficient for the "current plan" card, see Notes (L3.8) | `GetSubscriptionProvider` | `organization.read` |
| GET | `/api/organizations/{organizationId}/billing/payment-method` | Get the saved Stripe card (brand, last 4, expiry); `hasPaymentMethod: false` when none | `GetPaymentMethodProvider` | `organization.read` |
| GET | `/api/organizations/{organizationId}/billing/invoices` | List recent Stripe invoices (date, amount, status, hosted/PDF links) | `GetInvoicesProvider` | `organization.read` |
| GET | `/api/billing/pricing` | List display pricing (monthly/yearly amounts) for every payable plan | `GetPricingProvider` | `ROLE_USER` |
| POST | `/api/billing/webhook` | Receive and reconcile Stripe webhook events (public; signature verified) | `StripeWebhookController` | public |

`checkout`/`portal` return a URL the client redirects the browser to. The actual
plan change is applied by the webhook, never by these endpoints. Return URLs are
built server-side from `APP_FRONTEND_URL` (clients cannot inject redirects) and
point at `…/organizations/{id}/settings?tab=subscription`, with
`&checkout=success|cancel` after Checkout. Successful returns also carry
`checkoutPlan` and `checkoutInterval`, used only to compare the expected state
with the subscription API. The return itself never confirms payment or grants access.

`payment-method` is a **pure read model**: there is no local table, no input DTO
and no write path. `GetOrganizationPaymentMethodHandler` looks up the local
subscription for its `stripeCustomerId`, then `StripeGatewayAdapter::getPaymentMethod()`
reads the customer's `invoice_settings.default_payment_method` (expanded),
falling back to the customer's most recent attached card. Card details are
**never** collected, transmitted or stored by this application in the other
direction — brand/last 4/expiry only ever flow *from* Stripe. The client's
"Update card" action must redirect to the hosted Billing Portal
(`POST …/billing/portal`), never post card data to this API.

## Webhook & reconciliation

`HandleStripeWebhookHandler` reacts only to subscription lifecycle events:

- `customer.subscription.created`, `customer.subscription.updated` and
  `customer.subscription.deleted` trigger a fresh, fully paginated read of the
  customer's subscriptions from Stripe. Event snapshots never overwrite current state.
- any other event → no-op.

The environment (`livemode`) must match the configured Stripe key. Under one
organization-scoped PostgreSQL advisory lock, Billing rereads the local mapping,
checks the event receipt, fetches current remote state and selects the newest
subscription granting access (`active`, `trialing`, `past_due`), or the newest
subscription otherwise. An old cancellation or a newer abandoned Checkout cannot
replace a live subscription. Unknown current prices and remote read failures cause
a retryable failure, never a downgrade based on an incomplete read.

Subscription projection, organization plan and event receipt commit together in
`main`. Receipts are unique by `(event_id, live_mode)` in `billing_stripe_events`;
they contain identifiers and timestamps, not raw Stripe payloads. Technical failures
roll back all local writes so the same event can be retried. Cross-database and
external effects are outside this local atomicity guarantee.

The organization is resolved from the event `metadata.organization_id`,
**cross-checked against** the locally stored `stripe_customer_id` → organization
mapping. The metadata is not authoritative: `StripeGatewayAdapter` writes it when
a checkout session or subscription is created, but it then lives on Stripe's
side, is editable from the Stripe dashboard, and rides every later event for that
customer. The mapping is the record we control.

The three cases:

| Metadata | Local mapping | Outcome |
|---|---|---|
| absent | present | the mapping wins |
| present | absent | accepted only if the organization has no different local customer |
| present | present and **different** | **event ignored**, logged as a warning |

Neither side is preferred on disagreement. The same checks apply to every
subscription returned by the remote read; conflicting customer, organization or
environment data cannot update the local projection.

The refusal is logged through `LoggerPort` rather than thrown: an exception would
make Stripe retry the same contradictory event indefinitely, and the mismatch
needs a human to look at it, not a retry.

Replaying a successfully committed event is a no-op, including across workers.

`cancel` / `resume` schedule the change on Stripe (`cancel_at_period_end`) **and**
mirror the flag on the local aggregate immediately so the UI reflects it without
waiting for the reconciling webhook.
Checkout creation, cancellation and resumption take the same organization lock
as reconciliation. Customer creation uses a stable organization idempotency key.

## Inter-Module Usage

Billing publishes two outbound ports that the **Organization** module implements
(adapters in `Organization\Infrastructure\Adapter\Billing`), wired in
`config/modules/billing.yaml`:

- `Billing\Application\Port\Outbound\OrganizationPlanAssignmentPort` →
  `OrganizationPlanAssignmentAdapter`: resolves the stable plan key to its id and
  dispatches the existing `ChangeOrganizationPlanCommand`, so a paid plan is
  applied through the same validated path as a self-service change.
- `Billing\Application\Port\Outbound\OrganizationAccessPort` →
  `OrganizationAccessAdapter`: delegates to `OrganizationAuthorizationPort` so
  Billing enforces organization permissions without depending on Organization
  internals.

**Key invariant (enforced in `Organization`):** self-service
`PATCH /api/organizations/{id}/plan` is restricted to the **default (free) plan
only**; paid plans must go through Checkout. The webhook bypasses that processor
(it dispatches the command directly), so it can still assign paid plans.

## Persistence

- Table: `billing_subscriptions` (main database), one row per organization
  (unique on `organization_id`; indexed on `stripe_customer_id` and
  `stripe_subscription_id`).
- Columns: `id`, `organization_id`, `stripe_customer_id`,
  `stripe_subscription_id` (nullable), `status`, `plan_key` (nullable),
  `billing_interval` (nullable — named to avoid the reserved SQL word),
  `current_period_end` (nullable), `cancel_at_period_end`, `created_at`,
  `updated_at`.
- Migration: `migrations/main/Version20260620120000.php`.
- Repository: `Billing\Infrastructure\Persistence\Doctrine\Repository\SubscriptionRepository`.

The row is the local projection of the Stripe state; Stripe remains the source of
truth. A row is created at checkout time (status `incomplete`) to link the
organization to its Stripe customer before any payment is confirmed.

The payment method read model has **no table of its own** — it needs none, the
same way invoices don't: `billing_subscriptions.stripe_customer_id` is the only
local piece of state involved, and every card field is read live from Stripe on
each request.

## Architecture

- **Presentation**: API Platform resources (`OrganizationBillingResource`,
  `BillingPricingResource`, `BillingInvoicesResource`), providers, processors,
  input/output DTOs, and the webhook controller (`StripeWebhookController`).
- **Application**: command/query use cases (`StartCheckout`, `StartPortal`,
  `CancelSubscription`, `ResumeSubscription`, `HandleStripeWebhook`,
  `GetOrganizationSubscription`, `GetOrganizationInvoices`,
  `GetOrganizationPaymentMethod`), outbound ports, and the `BillingPriceCatalog`
  service.
- **Domain**: `Subscription` aggregate + value objects (`SubscriptionId`,
  `SubscriptionStatus`, `BillingInterval`) + exceptions
  (`InvalidWebhookSignatureException`, `BillingCustomerNotFoundException`,
  `NoActiveSubscriptionException`, `BillingGatewayUnavailableException`).
- **Infrastructure**: Doctrine record/mapper/repository and the Stripe gateway
  adapter (`StripeGatewayAdapter`).

`StripeGatewayAdapter` is the **only** class that imports `\Stripe\*`; everything
else depends on the `StripeGatewayPort` contract, keeping the Stripe SDK out of
the domain and application layers.

## Configuration

- Service wiring: `config/modules/billing.yaml` (handlers tagged
  `messenger.message_handler`, ports aliased, cross-module adapters bound).
- Parameters & price catalog: `config/packages/billing.yaml` — exposes
  `billing.stripe_secret_key`, `billing.stripe_webhook_secret`,
  `billing.frontend_url`, `billing.currency`, and the `billing.prices` map
  (`plan_key → {month: {priceId, amount}, year: {priceId, amount}}`).
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`.
- API resource path: `config/packages/api_platform.yaml`.
- Webhook route: `config/routes/billing.yaml`.
- Security: `config/packages/security.yaml` — a dedicated `billing_webhook`
  firewall (`pattern: ^/api/billing/webhook$`, `security: false`) **before** the
  `api` firewall, and a `PUBLIC_ACCESS` `access_control` rule for that path.

Required env vars (kept in the git-ignored `.env`; `.env.example` is the template):

- `STRIPE_SECRET_KEY` (`sk_test_…` / `sk_live_…`)
- `STRIPE_WEBHOOK_SECRET` (`whsec_…`)
- `APP_FRONTEND_URL` (return links)
- `STRIPE_CURRENCY` (e.g. `eur`)
- `STRIPE_PRICE_PRO_MONTHLY`, `STRIPE_PRICE_PRO_YEARLY`,
  `STRIPE_PRICE_MAX_MONTHLY`, `STRIPE_PRICE_MAX_YEARLY` (+ matching
  `…_AMOUNT` display amounts in the currency's smallest unit)

In development the `stripe-cli` compose service forwards webhooks to
`http://app:8000/api/billing/webhook`; copy the signing secret it prints into
`STRIPE_WEBHOOK_SECRET`.

## Testing

- Canonical unit tests: `tests/Unit/Billing`; PostgreSQL concurrency tests:
  `tests/Integration/Billing`. The older `tests/Billing` tree is not in the suite.
- Covered:
  - `Subscription` aggregate (start, sync, cancel/resume, mark canceled, status
    access rules),
  - `BillingPriceCatalog` (price lookup + reverse resolve),
  - `HandleStripeWebhook` (active → paid plan, deleted → free, unrelated no-op),
  - `CancelSubscription` / `ResumeSubscription` (Stripe call + local mirror,
    `409` when no live subscription),
  - `GetOrganizationInvoices` (empty without customer, gateway list otherwise),
  - `GetOrganizationPaymentMethod` (null without customer, null without a saved
    card, forwards the gateway's normalized payment method otherwise),
  - `GetPaymentMethodProvider` (missing organization id, missing permission,
    payment method present/absent mapping, `503` degradation on
    `BillingGatewayUnavailableException`),
  - `GetOrganizationSubscription` (L3.8: `planName`/`currency`/`monthlyAmount`/
    `yearlyAmount` resolved with vs without a `billing_subscriptions` row, the
    organization's current plan taking precedence over the subscription's
    stale billed plan key, and null pricing/name when the current plan
    cannot be resolved at all),
  - `GetSubscriptionProvider` (missing organization id, missing permission,
    the L3.8 `planName`/pricing fields mapped through to the output
    unchanged).
- Functional: `tests/Functional/Api/BillingApiTest.php` — smoke-tests that the
  `payment-method` and `subscription` routes exist and require authentication
  (`401`/`403`, not `404`), matching the pattern used across the other
  modules' functional API tests.
- Stripe adapter tests cover event identity/environment and pagination using the
  SDK's mocked HTTP boundary. Reconciliation tests cover duplicates, reordered
  lifecycle events, mapping conflicts, failed remote reads and unknown prices.
- Independent PostgreSQL connections verify organization lock exclusion, durable
  deduplication, rollback/retry and refreshing an already-managed projection.
- The Billing module is phpstan-clean at `level: max`.

## Error Codes

- Checkout: `400` when the plan is not payable / cadence unknown, `403` on
  missing permission, `201` on success.
- Portal: `409` when the organization has no Stripe customer yet
  (`BillingCustomerNotFoundException`), `403` on missing permission.
- Cancel / Resume: `409` when there is no live subscription
  (`NoActiveSubscriptionException`), `403` on missing permission, `200` with the
  refreshed subscription on success.
- Subscription / Invoices / Pricing: `403` on missing permission. Invoices
  returns an empty list when the organization has no Stripe customer yet.
- Payment method: `403` on missing permission; `200` with `hasPaymentMethod: false`
  (never `404`) when the organization has no Stripe customer yet or the
  customer has no saved card — this is the normal state for a free-plan
  organization. `503` (`BillingGatewayUnavailableException`) when Stripe cannot
  be reached, instead of a raw `500`.
- Webhook: `204` on success, `400` on an invalid signature
  (`InvalidWebhookSignatureException`); any other failure surfaces as `5xx` so
  Stripe retries.

Command-bus failures are unwrapped via the `ResolvesMessengerFailure` trait
(`MessengerRuntimeException` → `HandlerFailedException` → domain exception) so
processors map them to the right HTTP status.

## Notes

- Webhook delivery order is irrelevant to the local projection: each unprocessed
  event reconciles current remote state under the same lock as billing commands.
- Deploy the additive `main` event-journal migration before webhook workers.
  A rollback that drops receipts loses deduplication history and requires pausing delivery.
- An abandoned Checkout leaves an `incomplete` row (the Stripe customer is reused
  on the next attempt).
- **Self-sufficient subscription payload (L3.8)**: `SubscriptionOutput`/
  `GetOrganizationSubscriptionResult` add `planName` (resolved through
  `OrganizationPlanPort::findCurrentPlan()`, the same cross-module port
  already used for `planKey`) and `currency`/`monthlyAmount`/`yearlyAmount`
  (resolved through the existing `BillingPriceCatalog::pricingFor()` — no new
  price representation; amounts stay integers in the currency's smallest
  unit, mirroring `PlanPricingOutput`/Stripe, never a float). Both are
  resolved from the organization's CURRENTLY assigned plan (falling back to
  the catalog default), the same resolution rule `planKey` already used, so
  the payload stays correct even without a local `billing_subscriptions` row
  (e.g. an organization still on the free plan that never went through
  Checkout) — `monthlyAmount`/`yearlyAmount` are simply `null` for a
  non-payable plan (the free plan), which callers render as "no charge", not
  as missing data. `GET /billing/pricing` and `GET /plans` are unchanged and
  remain byte-for-byte backward compatible; this is purely an additive
  enrichment of the subscription read model so a client can render the
  "current plan" card from one call. The plan's marketing `tagline`/`perks`
  (see `src/Organization/MODULE.md`, `PlanPresentationCatalog`) are
  deliberately **not** duplicated here — they are display copy for the plan
  *catalog* card, fetched from `/plans` only when that card is actually
  shown, whereas the subscription card only ever needs the plan's name and
  price.
