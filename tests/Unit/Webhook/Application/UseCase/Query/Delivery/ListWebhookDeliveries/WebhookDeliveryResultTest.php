<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries\WebhookDeliveryResult;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};

/**
 * Test WebhookDeliveryResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryResult::class)]
final class WebhookDeliveryResultTest extends TestCase
{
  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a10';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a11';

  #[Test]
  public function testConstructorExposesEveryField(): void
  {
    $nextRetryAt = new DateTimeImmutable('2026-07-18T01:00:00+00:00');
    $deliveredAt = new DateTimeImmutable('2026-07-18T02:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-07-18T00:00:00+00:00');

    $result = new WebhookDeliveryResult(
      id: self::DELIVERY_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      eventType: 'intervention.published',
      status: 'delivered',
      attempts: 2,
      httpStatus: 200,
      lastError: 'timeout',
      nextRetryAt: $nextRetryAt,
      deliveredAt: $deliveredAt,
      createdAt: $createdAt,
    );

    self::assertSame(self::DELIVERY_ID, $result->id);
    self::assertSame(self::SUBSCRIPTION_ID, $result->subscriptionId);
    self::assertSame('intervention.published', $result->eventType);
    self::assertSame('delivered', $result->status);
    self::assertSame(2, $result->attempts);
    self::assertSame(200, $result->httpStatus);
    self::assertSame('timeout', $result->lastError);
    self::assertSame($nextRetryAt, $result->nextRetryAt);
    self::assertSame($deliveredAt, $result->deliveredAt);
    self::assertSame($createdAt, $result->createdAt);
  }

  #[Test]
  public function testFromDomainProjectsTheAggregate(): void
  {
    $createdAt = new DateTimeImmutable('2026-07-18T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-07-18T00:05:00+00:00');
    $nextRetryAt = new DateTimeImmutable('2026-07-18T00:10:00+00:00');
    $deliveredAt = new DateTimeImmutable('2026-07-18T00:15:00+00:00');

    $delivery = WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString(self::DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a12',
      eventType: 'intervention.published',
      eventId: 'evt-1',
      payload: ['id' => 'evt-1'],
      status: WebhookDeliveryStatus::DELIVERED,
      attempts: 3,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      httpStatus: 204,
      lastError: null,
      nextRetryAt: $nextRetryAt,
      deliveredAt: $deliveredAt,
    );

    $result = WebhookDeliveryResult::fromDomain($delivery);

    self::assertSame(self::DELIVERY_ID, $result->id);
    self::assertSame(self::SUBSCRIPTION_ID, $result->subscriptionId);
    self::assertSame('intervention.published', $result->eventType);
    self::assertSame(WebhookDeliveryStatus::DELIVERED->value, $result->status);
    self::assertSame(3, $result->attempts);
    self::assertSame(204, $result->httpStatus);
    self::assertNull($result->lastError);
    self::assertSame($nextRetryAt, $result->nextRetryAt);
    self::assertSame($deliveredAt, $result->deliveredAt);
    self::assertSame($createdAt, $result->createdAt);
  }
}
