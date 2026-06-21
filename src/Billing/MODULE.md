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
- Read the organization's current subscription state.
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
| GET | `/api/organizations/{organizationId}/billing/subscription` | Get the current subscription state (status, plan, renewal, scheduled cancel) | `GetSubscriptionProvider` | `organization.read` |
| GET | `/api/organizations/{organizationId}/billing/invoices` | List recent Stripe invoices (date, amount, status, hosted/PDF links) | `GetInvoicesProvider` | `organization.read` |
| GET | `/api/billing/pricing` | List display pricing (monthly/yearly amounts) for every payable plan | `GetPricingProvider` | `ROLE_USER` |
| POST | `/api/billing/webhook` | Receive and reconcile Stripe webhook events (public; signature verified) | `StripeWebhookController` | public |

`checkout`/`portal` return a URL the client redirects the browser to. The actual
plan change is applied by the webhook, never by these endpoints. Return URLs are
built server-side from `APP_FRONTEND_URL` (clients cannot inject redirects) and
point at `…/organizations/{id}/settings?tab=subscription`, with
`&checkout=success|cancel` after Checkout.

## Webhook & reconciliation

`HandleStripeWebhookHandler` reacts only to subscription lifecycle events:

- `customer.subscription.created` / `customer.subscription.updated` → upsert the
  local projection (status, `plan_key` resolved from the price, cadence,
  `current_period_end`, `cancel_at_period_end`) **and** apply the plan to the
  organization. The plan applied is the paid `plan_key` when the status grants
  access (`active`, `trialing`, `past_due`), otherwise `free`.
- `customer.subscription.deleted` → mark the projection canceled and downgrade
  the organization to `free`.
- any other event → no-op.

The organization is resolved from the event `metadata.organization_id`, falling
back to the locally stored `stripe_customer_id` → organization mapping. The flow
is idempotent: replaying an event converges to the same state.

`cancel` / `resume` schedule the change on Stripe (`cancel_at_period_end`) **and**
mirror the flag on the local aggregate immediately so the UI reflects it without
waiting for the reconciling webhook.

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

## Architecture

- **Presentation**: API Platform resources (`OrganizationBillingResource`,
  `BillingPricingResource`, `BillingInvoicesResource`), providers, processors,
  input/output DTOs, and the webhook controller (`StripeWebhookController`).
- **Application**: command/query use cases (`StartCheckout`, `StartPortal`,
  `CancelSubscription`, `ResumeSubscription`, `HandleStripeWebhook`,
  `GetOrganizationSubscription`, `GetOrganizationInvoices`), outbound ports, and
  the `BillingPriceCatalog` service.
- **Domain**: `Subscription` aggregate + value objects (`SubscriptionId`,
  `SubscriptionStatus`, `BillingInterval`) + exceptions
  (`InvalidWebhookSignatureException`, `BillingCustomerNotFoundException`,
  `NoActiveSubscriptionException`).
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

## Error Mapping

- Checkout: `400` when the plan is not payable / cadence unknown, `403` on
  missing permission, `201` on success.
- Portal: `409` when the organization has no Stripe customer yet
  (`BillingCustomerNotFoundException`), `403` on missing permission.
- Cancel / Resume: `409` when there is no live subscription
  (`NoActiveSubscriptionException`), `403` on missing permission, `200` with the
  refreshed subscription on success.
- Subscription / Invoices / Pricing: `403` on missing permission. Invoices
  returns an empty list when the organization has no Stripe customer yet.
- Webhook: `204` on success, `400` on an invalid signature
  (`InvalidWebhookSignatureException`); any other failure surfaces as `5xx` so
  Stripe retries.

Command-bus failures are unwrapped via the `ResolvesMessengerFailure` trait
(`MessengerRuntimeException` → `HandlerFailedException` → domain exception) so
processors map them to the right HTTP status.

## Testing

- Unit tests: `tests/Billing`
- Covered:
  - `Subscription` aggregate (start, sync, cancel/resume, mark canceled, status
    access rules),
  - `BillingPriceCatalog` (price lookup + reverse resolve),
  - `HandleStripeWebhook` (active → paid plan, deleted → free, unrelated no-op),
  - `CancelSubscription` / `ResumeSubscription` (Stripe call + local mirror,
    `409` when no live subscription),
  - `GetOrganizationInvoices` (empty without customer, gateway list otherwise).
- The Billing module is phpstan-clean at `level: max`.

## Notes

- Webhook ordering: Stripe may deliver events out of order. The upsert converges
  to the final state, but there is no per-event timestamp guard — an older
  duplicate arriving after a newer event could transiently regress
  `cancel_at_period_end` / `status` until the next event.
- The webhook `save()` and the cross-module `assignPlanByKey()` run in separate
  transactions; they are eventually consistent, reconciled by subsequent events.
- `livemode` is not inspected; isolation between test and live relies on using
  separate keys per environment.
- An abandoned Checkout leaves an `incomplete` row (the Stripe customer is reused
  on the next attempt).
