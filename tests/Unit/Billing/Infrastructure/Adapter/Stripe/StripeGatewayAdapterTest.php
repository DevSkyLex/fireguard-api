<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Infrastructure\Adapter\Stripe;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Domain\Exception\InvalidWebhookSignatureException;
use Billing\Infrastructure\Adapter\Stripe\StripeGatewayAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function hash_hmac;
use function json_encode;
use function sprintf;
use function time;

use const JSON_THROW_ON_ERROR;

/**
 * Test StripeGatewayAdapterTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StripeGatewayAdapterTest extends TestCase
{
  private const string SECRET_KEY = 'sk_test_dummy';

  private const string WEBHOOK_SECRET = 'whsec_test_secret';

  #[Test]
  public function ensureCustomerReturnsTheExistingIdentifierWhenProvided(): void
  {
    self::assertSame(
      'cus_existing',
      $this->adapter()->ensureCustomer('org-1', 'cus_existing'),
    );
  }

  #[Test]
  public function parseEventThrowsOnAnInvalidSignature(): void
  {
    $this->expectException(InvalidWebhookSignatureException::class);

    $this->adapter()->parseEvent('{"type":"invoice.paid"}', 't=0,v1=deadbeef');
  }

  #[Test]
  public function parseEventReturnsABareEventForANonSubscriptionType(): void
  {
    $payload = json_encode(['type' => 'invoice.paid'], JSON_THROW_ON_ERROR);

    $event = $this->adapter()->parseEvent($payload, $this->signedHeader($payload));

    self::assertInstanceOf(StripeEvent::class, $event);
    self::assertSame('invoice.paid', $event->type);
    self::assertNull($event->organizationId);
    self::assertNull($event->customerId);
    self::assertNull($event->subscriptionId);
    self::assertNull($event->status);
    self::assertNull($event->priceId);
    self::assertNull($event->currentPeriodEnd);
    self::assertFalse($event->cancelAtPeriodEnd);
  }

  #[Test]
  public function parseEventMapsAFullySpecifiedSubscriptionPayload(): void
  {
    $payload = json_encode([
      'id' => 'evt_1',
      'object' => 'event',
      'type' => 'customer.subscription.updated',
      'data' => [
        'object' => [
          'id' => 'sub_123',
          'customer' => 'cus_123',
          'status' => 'active',
          'cancel_at_period_end' => true,
          'current_period_end' => 1_800_000_000,
          'metadata' => ['organization_id' => 'org-42'],
          'items' => ['data' => [['price' => ['id' => 'price_pro_m']]]],
        ],
      ],
    ], JSON_THROW_ON_ERROR);

    $event = $this->adapter()->parseEvent($payload, $this->signedHeader($payload));

    self::assertSame('customer.subscription.updated', $event->type);
    self::assertSame('org-42', $event->organizationId);
    self::assertSame('cus_123', $event->customerId);
    self::assertSame('sub_123', $event->subscriptionId);
    self::assertSame('active', $event->status);
    self::assertSame('price_pro_m', $event->priceId);
    self::assertSame(1_800_000_000, $event->currentPeriodEnd);
    self::assertTrue($event->cancelAtPeriodEnd);
  }

  #[Test]
  public function parseEventFallsBackToTheItemPeriodEndAndDropsEmptyStrings(): void
  {
    $payload = json_encode([
      'type' => 'customer.subscription.updated',
      'data' => [
        'object' => [
          'id' => 'sub_x',
          'customer' => '',
          'status' => 'trialing',
          'metadata' => ['organization_id' => ''],
          'items' => ['data' => [['price' => ['id' => 'price_y'], 'current_period_end' => 1_700_000_000]]],
        ],
      ],
    ], JSON_THROW_ON_ERROR);

    $event = $this->adapter()->parseEvent($payload, $this->signedHeader($payload));

    self::assertSame('sub_x', $event->subscriptionId);
    self::assertNull($event->customerId);
    self::assertNull($event->organizationId);
    self::assertSame('trialing', $event->status);
    self::assertSame('price_y', $event->priceId);
    self::assertSame(1_700_000_000, $event->currentPeriodEnd);
    self::assertFalse($event->cancelAtPeriodEnd);
  }

  #[Test]
  public function parseEventNarrowsNonStringAndMissingFieldsToNull(): void
  {
    $payload = json_encode([
      'type' => 'customer.subscription.created',
      'data' => [
        'object' => [
          'customer' => 456,
          'metadata' => ['organization_id' => 789],
        ],
      ],
    ], JSON_THROW_ON_ERROR);

    $event = $this->adapter()->parseEvent($payload, $this->signedHeader($payload));

    self::assertSame('customer.subscription.created', $event->type);
    self::assertNull($event->organizationId);
    self::assertNull($event->customerId);
    self::assertNull($event->subscriptionId);
    self::assertNull($event->status);
    self::assertNull($event->priceId);
    self::assertNull($event->currentPeriodEnd);
    self::assertFalse($event->cancelAtPeriodEnd);
  }

  private function adapter(): StripeGatewayAdapter
  {
    return new StripeGatewayAdapter(self::SECRET_KEY, self::WEBHOOK_SECRET);
  }

  private function signedHeader(string $payload): string
  {
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET);

    return sprintf('t=%d,v1=%s', $timestamp, $signature);
  }
}
