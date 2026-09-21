<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Infrastructure\Adapter\Stripe;

use Billing\Application\Contract\Stripe\StripeEvent;
use Billing\Domain\Exception\InvalidWebhookSignatureException;
use Billing\Infrastructure\Adapter\Stripe\StripeGatewayAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

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
      'created' => 1_800_000_001,
      'livemode' => false,
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
    self::assertSame('evt_1', $event->eventId);
    self::assertSame(1_800_000_001, $event->created);
    self::assertFalse($event->liveMode);
  }

  #[Test]
  public function identifiesLiveAndTestKeys(): void
  {
    self::assertFalse($this->adapter()->isLiveMode());
    self::assertTrue(new StripeGatewayAdapter('sk_live_dummy', self::WEBHOOK_SECRET)->isLiveMode());
    self::assertTrue(new StripeGatewayAdapter('rk_live_dummy', self::WEBHOOK_SECRET)->isLiveMode());
  }

  #[Test]
  public function readsEveryPageIncludingCanceledSubscriptions(): void
  {
    $previous = ApiRequestor::httpClient();
    $http = $this->createMock(ClientInterface::class);
    $requests = 0;
    $http->expects(self::exactly(2))->method('request')->willReturnCallback(
      static function (string $method, string $url, array $headers, array $params) use (&$requests): array {
        ++$requests;
        self::assertSame('get', $method);
        self::assertStringEndsWith('/v1/subscriptions', $url);
        self::assertSame('cus_123', $params['customer']);
        self::assertSame('all', $params['status']);
        if (2 === $requests) {
          self::assertSame('sub_1', $params['starting_after']);
        }

        return [json_encode([
          'object' => 'list', 'url' => '/v1/subscriptions', 'has_more' => 1 === $requests,
          'data' => [[
            'object' => 'subscription', 'id' => 'sub_' . $requests,
            'customer' => 'cus_123', 'status' => 1 === $requests ? 'active' : 'canceled',
            'metadata' => ['organization_id' => 'org-42'], 'created' => 100 - $requests,
            'livemode' => false, 'cancel_at_period_end' => false,
            'items' => ['data' => [['price' => ['id' => 'price_pro_m'], 'current_period_end' => 1_800_000_000]]],
          ]],
        ], JSON_THROW_ON_ERROR), 200, []];
      },
    );
    ApiRequestor::setHttpClient($http);

    try {
      $subscriptions = $this->adapter()->listSubscriptions('cus_123');
      self::assertCount(2, $subscriptions);
      self::assertSame('active', $subscriptions[0]->status);
      self::assertSame('canceled', $subscriptions[1]->status);
      self::assertSame('org-42', $subscriptions[1]->organizationId);
      self::assertSame(1_800_000_000, $subscriptions[0]->currentPeriodEnd);
    } finally {
      ApiRequestor::setHttpClient($previous);
    }
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
