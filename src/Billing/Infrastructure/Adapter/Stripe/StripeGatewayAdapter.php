<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Adapter\Stripe;

use Billing\Application\Port\Outbound\Stripe\{StripeEvent, StripeInvoice};
use Billing\Application\Port\Outbound\StripeGatewayPort;
use Billing\Domain\Exception\InvalidWebhookSignatureException;
use DateTimeImmutable;
use Stripe\{StripeClient, Webhook};
use Throwable;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function str_starts_with;
use function strtoupper;

/**
 * Adapter StripeGatewayAdapter.
 *
 * Concrete Stripe Billing integration backing {@see StripeGatewayPort}, built on
 * the official `stripe/stripe-php` SDK. Keeps all Stripe specifics — customers,
 * hosted Checkout, the Billing Portal and webhook signature verification — inside
 * the infrastructure layer.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StripeGatewayAdapter implements StripeGatewayPort
{
  // #region Properties
  /**
   * Property stripe.
   *
   * @since 1.0.0
   */
  private StripeClient $stripe;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StripeGatewayAdapter class.
   *
   * @since 1.0.0
   *
   * @param string $secretKey the Stripe secret API key
   * @param string $webhookSecret the Stripe webhook signing secret
   */
  public function __construct(
    string $secretKey,
    private string $webhookSecret,
  ) {
    $this->stripe = new StripeClient($secretKey);
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function ensureCustomer(string $organizationId, ?string $existingCustomerId): string
  {
    if (null !== $existingCustomerId && '' !== $existingCustomerId) {
      return $existingCustomerId;
    }

    $customer = $this->stripe->customers->create([
      'metadata' => ['organization_id' => $organizationId],
    ]);

    return $customer->id;
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
    $metadata = ['organization_id' => $organizationId, 'plan_key' => $planKey];

    $session = $this->stripe->checkout->sessions->create([
      'mode' => 'subscription',
      'customer' => $customerId,
      'line_items' => [['price' => $priceId, 'quantity' => 1]],
      'success_url' => $successUrl,
      'cancel_url' => $cancelUrl,
      'metadata' => $metadata,
      'subscription_data' => ['metadata' => $metadata],
    ]);

    return (string) $session->url;
  }

  /**
   * {@inheritDoc}
   */
  public function createBillingPortalSession(string $customerId, string $returnUrl): string
  {
    $session = $this->stripe->billingPortal->sessions->create([
      'customer' => $customerId,
      'return_url' => $returnUrl,
    ]);

    return (string) $session->url;
  }

  /**
   * {@inheritDoc}
   */
  public function parseEvent(string $payload, string $signatureHeader): StripeEvent
  {
    try {
      $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
    } catch (Throwable $exception) {
      throw InvalidWebhookSignatureException::create($exception);
    }

    $type = (string) $event->type;

    if (!str_starts_with($type, 'customer.subscription.')) {
      return new StripeEvent(type: $type);
    }

    $data = $event->data->object->toArray();

    return new StripeEvent(
      type: $type,
      organizationId: $this->stringOrNull($this->dig($data, 'metadata', 'organization_id')),
      customerId: $this->stringOrNull($this->dig($data, 'customer')),
      subscriptionId: $this->stringOrNull($this->dig($data, 'id')),
      status: $this->stringOrNull($this->dig($data, 'status')),
      priceId: $this->stringOrNull($this->dig($data, 'items', 'data', 0, 'price', 'id')),
      currentPeriodEnd: $this->periodEnd($data),
      cancelAtPeriodEnd: true === $this->dig($data, 'cancel_at_period_end'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function listInvoices(string $customerId, int $limit = 12): array
  {
    $collection = $this->stripe->invoices->all([
      'customer' => $customerId,
      'limit' => $limit,
    ]);

    $invoices = [];

    foreach ($collection->data as $invoice) {
      $data = $invoice->toArray();
      $created = $this->dig($data, 'created');

      $invoices[] = new StripeInvoice(
        id: $this->stringOrNull($this->dig($data, 'id')) ?? '',
        number: $this->stringOrNull($this->dig($data, 'number')),
        status: $this->stringOrNull($this->dig($data, 'status')) ?? 'open',
        amountPaid: $this->intOrZero($this->dig($data, 'amount_paid')),
        amountDue: $this->intOrZero($this->dig($data, 'amount_due')),
        currency: strtoupper($this->stringOrNull($this->dig($data, 'currency')) ?? 'eur'),
        createdAt: is_int($created) ? new DateTimeImmutable()->setTimestamp($created) : null,
        hostedInvoiceUrl: $this->stringOrNull($this->dig($data, 'hosted_invoice_url')),
        invoicePdf: $this->stringOrNull($this->dig($data, 'invoice_pdf')),
      );
    }

    return $invoices;
  }

  /**
   * {@inheritDoc}
   */
  public function scheduleCancellation(string $subscriptionId): void
  {
    $this->stripe->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);
  }

  /**
   * {@inheritDoc}
   */
  public function resumeCancellation(string $subscriptionId): void
  {
    $this->stripe->subscriptions->update($subscriptionId, ['cancel_at_period_end' => false]);
  }

  /**
   * Method periodEnd.
   *
   * Reads the current period end, tolerating both the legacy top-level field and
   * the per-item placement used by newer Stripe API versions.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the subscription payload
   *
   * @return ?int the period end as a UNIX timestamp, or null
   */
  private function periodEnd(array $data): ?int
  {
    $topLevel = $this->dig($data, 'current_period_end');
    if (is_int($topLevel)) {
      return $topLevel;
    }

    $item = $this->dig($data, 'items', 'data', 0, 'current_period_end');

    return is_int($item) ? $item : null;
  }

  /**
   * Method dig.
   *
   * Safely walks a nested array along the given keys, returning null as soon as a
   * segment is missing or not an array. Keeps the Stripe payload handling free of
   * unchecked offset access on mixed values.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the payload to read from
   * @param int|string ...$path the successive keys to follow
   *
   * @return mixed the resolved value, or null
   */
  private function dig(array $data, int|string ...$path): mixed
  {
    $value = $data;

    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        return null;
      }

      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Method stringOrNull.
   *
   * Narrows a mixed value to a non-empty string, or null otherwise.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value to narrow
   *
   * @return ?string the string value, or null
   */
  private function stringOrNull(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method intOrZero.
   *
   * Narrows a mixed value to an integer, defaulting to zero.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value to narrow
   *
   * @return int the integer value, or zero
   */
  private function intOrZero(mixed $value): int
  {
    return is_int($value) ? $value : 0;
  }
  // #endregion
}
