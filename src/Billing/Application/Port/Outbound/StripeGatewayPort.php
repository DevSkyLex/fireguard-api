<?php

declare(strict_types=1);

namespace Billing\Application\Port\Outbound;

use Billing\Application\Contract\Stripe\{StripeEvent, StripeInvoice, StripePaymentMethod};

/**
 * Port StripeGatewayPort.
 *
 * Abstraction over the Stripe Billing API. Hides the Stripe SDK from the
 * application layer so use cases depend only on this contract.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface StripeGatewayPort
{
  // #region Methods
  /**
   * Method ensureCustomer.
   *
   * Returns a Stripe customer identifier for the organization, reusing the
   * existing one when provided or creating a new customer (tagged with the
   * organization id in metadata) otherwise.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $existingCustomerId the previously stored customer id, when any
   *
   * @return string the Stripe customer identifier
   */
  public function ensureCustomer(string $organizationId, ?string $existingCustomerId): string;

  /**
   * Method createCheckoutSession.
   *
   * Creates a hosted Checkout session in subscription mode and returns its URL.
   * The organization id and plan key are attached as metadata on both the session
   * and the resulting subscription so webhooks can resolve them.
   *
   * @since 1.0.0
   *
   * @param string $customerId the Stripe customer identifier
   * @param string $priceId the Stripe price identifier to subscribe to
   * @param string $organizationId the organization identifier
   * @param string $planKey the target plan key
   * @param string $successUrl the post-payment return URL
   * @param string $cancelUrl the canceled-payment return URL
   *
   * @return string the hosted Checkout URL
   */
  public function createCheckoutSession(
    string $customerId,
    string $priceId,
    string $organizationId,
    string $planKey,
    string $successUrl,
    string $cancelUrl,
  ): string;

  /**
   * Method createBillingPortalSession.
   *
   * Creates a hosted Billing Portal session and returns its URL.
   *
   * @since 1.0.0
   *
   * @param string $customerId the Stripe customer identifier
   * @param string $returnUrl the URL Stripe returns the user to
   *
   * @return string the hosted Billing Portal URL
   */
  public function createBillingPortalSession(string $customerId, string $returnUrl): string;

  /**
   * Method parseEvent.
   *
   * Verifies the webhook signature and returns a normalized event projection.
   *
   * @since 1.0.0
   *
   * @param string $payload the raw request body
   * @param string $signatureHeader the value of the Stripe-Signature header
   *
   * @throws \Billing\Domain\Exception\InvalidWebhookSignatureException when the signature is invalid
   *
   * @return StripeEvent the normalized event
   */
  public function parseEvent(string $payload, string $signatureHeader): StripeEvent;

  /**
   * Method listInvoices.
   *
   * Lists the most recent invoices of a Stripe customer, newest first.
   *
   * @since 1.0.0
   *
   * @param string $customerId the Stripe customer identifier
   * @param int $limit the maximum number of invoices to return
   *
   * @return list<StripeInvoice> the normalized invoices
   */
  public function listInvoices(string $customerId, int $limit = 12): array;

  /**
   * Method scheduleCancellation.
   *
   * Flags a Stripe subscription to cancel at the end of the current billing
   * period, keeping access until then.
   *
   * @since 1.0.0
   *
   * @param string $subscriptionId the Stripe subscription identifier
   */
  public function scheduleCancellation(string $subscriptionId): void;

  /**
   * Method resumeCancellation.
   *
   * Clears a scheduled cancellation on a Stripe subscription so it renews
   * normally.
   *
   * @since 1.0.0
   *
   * @param string $subscriptionId the Stripe subscription identifier
   */
  public function resumeCancellation(string $subscriptionId): void;

  /**
   * Method getPaymentMethod.
   *
   * Returns the customer's default saved card (brand, last 4 digits, expiry),
   * or null when the customer has none. Card details never cross this
   * boundary in the other direction: this application only ever reads what
   * Stripe already stores.
   *
   * @since 1.0.0
   *
   * @param string $customerId the Stripe customer identifier
   *
   * @throws \Billing\Domain\Exception\BillingGatewayUnavailableException when Stripe cannot be reached
   *
   * @return ?StripePaymentMethod the normalized payment method, or null when none is saved
   */
  public function getPaymentMethod(string $customerId): ?StripePaymentMethod;
  // #endregion
}
