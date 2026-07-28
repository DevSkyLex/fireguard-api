<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Stripe;

use Billing\Application\Contract\Stripe\{StripeEvent, StripePaymentMethod};
use Billing\Application\Port\Outbound\StripeGatewayPort;
use Billing\Domain\Exception\InvalidWebhookSignatureException;

use function bin2hex;
use function random_bytes;
use function str_starts_with;

/**
 * Adapter FakeStripeGatewayAdapter.
 *
 * In-memory stand-in for {@see StripeGatewayAdapter}, bound to
 * {@see StripeGatewayPort} only in the `test` environment (see
 * config/modules/billing.yaml). Keeps the hermetic test suite — no real
 * network calls, no dependency on a `STRIPE_SECRET_KEY` — while every
 * Billing handler still resolves a working adapter through the same port.
 *
 * Deliberately minimal: it satisfies the E2E flows that never reach an
 * active subscription (fresh organizations have none), rather than
 * simulating the full Stripe object graph.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FakeStripeGatewayAdapter implements StripeGatewayPort
{
  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function ensureCustomer(string $organizationId, ?string $existingCustomerId): string
  {
    if (null !== $existingCustomerId && '' !== $existingCustomerId) {
      return $existingCustomerId;
    }

    return 'cus_fake_' . bin2hex(random_bytes(8));
  }

  /**
   * {@inheritDoc}
   */
  public function createCheckoutSession(
    string $customerId,
    string $priceId,
    string $organizationId,
    string $planKey,
    string $successUrl,
    string $cancelUrl,
  ): string {
    return 'https://checkout.stripe.test/pay/cs_fake_' . bin2hex(random_bytes(8));
  }

  /**
   * {@inheritDoc}
   */
  public function createBillingPortalSession(string $customerId, string $returnUrl): string
  {
    return 'https://billing.stripe.test/session/bps_fake_' . bin2hex(random_bytes(8));
  }

  /**
   * {@inheritDoc}
   *
   * No real signature to verify without a Stripe SDK call, so this checks
   * the same thing Stripe's own `Webhook::constructEvent` would reject
   * first: a missing or obviously-not-a-signature header. Good enough to
   * exercise the controller's 400 path without a real webhook secret.
   */
  public function parseEvent(string $payload, string $signatureHeader): StripeEvent
  {
    if ('' === $signatureHeader || !str_starts_with($signatureHeader, 't=')) {
      throw InvalidWebhookSignatureException::create();
    }

    return new StripeEvent(type: 'fake.event');
  }

  /**
   * {@inheritDoc}
   */
  public function listInvoices(string $customerId, int $limit = 12): array
  {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function scheduleCancellation(string $subscriptionId): void
  {
  }

  /**
   * {@inheritDoc}
   */
  public function resumeCancellation(string $subscriptionId): void
  {
  }

  /**
   * {@inheritDoc}
   */
  public function getPaymentMethod(string $customerId): ?StripePaymentMethod
  {
    return null;
  }
  // #endregion
}
