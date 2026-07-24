<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\Model\Delivery;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\Model\Delivery\WebhookDelivery;
use Webhook\Domain\ValueObject\{WebhookDeliveryId, WebhookDeliveryStatus, WebhookSubscriptionId};

/**
 * Test WebhookDelivery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDelivery::class)]
final class WebhookDeliveryTest extends TestCase
{
  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a03';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itCreatesAPendingDeliveryWithZeroAttempts(): void
  {
    $delivery = $this->newDelivery();

    self::assertSame(self::DELIVERY_ID, (string) $delivery->id());
    self::assertSame(self::SUBSCRIPTION_ID, (string) $delivery->subscriptionId());
    self::assertSame(self::ORGANIZATION_ID, $delivery->organizationId());
    self::assertSame('intervention.published', $delivery->eventType());
    self::assertSame('event-1', $delivery->eventId());
    self::assertSame(['foo' => 'bar'], $delivery->payload());
    self::assertSame(WebhookDeliveryStatus::PENDING, $delivery->status());
    self::assertSame(0, $delivery->attempts());
    self::assertNull($delivery->httpStatus());
    self::assertNull($delivery->lastError());
    self::assertNull($delivery->nextRetryAt());
    self::assertNull($delivery->deliveredAt());
    self::assertEquals($delivery->createdAt(), $delivery->updatedAt());
  }

  #[Test]
  public function itMarksDeliveredAndClearsFailureState(): void
  {
    $delivery = $this->newDelivery();
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $delivery->markDelivered(200, $now);

    self::assertSame(WebhookDeliveryStatus::DELIVERED, $delivery->status());
    self::assertSame(1, $delivery->attempts());
    self::assertSame(200, $delivery->httpStatus());
    self::assertNull($delivery->lastError());
    self::assertNull($delivery->nextRetryAt());
    self::assertSame($now, $delivery->deliveredAt());
    self::assertSame($now, $delivery->updatedAt());
    self::assertTrue($delivery->status()->isTerminal());
  }

  #[Test]
  public function markDeliveredIsIdempotent(): void
  {
    $delivery = $this->newDelivery();
    $delivery->markDelivered(200, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $delivery->markDelivered(204, new DateTimeImmutable('2026-01-02T00:00:00+00:00'));

    self::assertSame(1, $delivery->attempts());
    self::assertSame(200, $delivery->httpStatus());
  }

  #[Test]
  public function itRecordsARetryableFailureAndStaysPending(): void
  {
    $delivery = $this->newDelivery();
    $retryAt = new DateTimeImmutable('2026-01-01T00:05:00+00:00');
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $delivery->recordRetryableFailure(503, 'Service Unavailable', $retryAt, $now);

    self::assertSame(WebhookDeliveryStatus::PENDING, $delivery->status());
    self::assertSame(1, $delivery->attempts());
    self::assertSame(503, $delivery->httpStatus());
    self::assertSame('Service Unavailable', $delivery->lastError());
    self::assertSame($retryAt, $delivery->nextRetryAt());
  }

  #[Test]
  public function itMarksTerminallyFailed(): void
  {
    $delivery = $this->newDelivery();
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $delivery->markFailed(500, 'boom', $now);

    self::assertSame(WebhookDeliveryStatus::FAILED, $delivery->status());
    self::assertSame(1, $delivery->attempts());
    self::assertSame(500, $delivery->httpStatus());
    self::assertSame('boom', $delivery->lastError());
    self::assertNull($delivery->nextRetryAt());
  }

  #[Test]
  public function itReopensAFailedDeliveryBackToPending(): void
  {
    $delivery = $this->newDelivery();
    $delivery->markFailed(500, 'boom', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $reopenedAt = new DateTimeImmutable('2026-01-03T00:00:00+00:00');
    $delivery->reopen($reopenedAt);

    self::assertSame(WebhookDeliveryStatus::PENDING, $delivery->status());
    self::assertSame(0, $delivery->attempts());
    self::assertNull($delivery->httpStatus());
    self::assertNull($delivery->lastError());
    self::assertNull($delivery->nextRetryAt());
    self::assertSame($reopenedAt, $delivery->updatedAt());
  }

  #[Test]
  public function aTerminalDeliveryCannotRecordAnotherFailure(): void
  {
    $delivery = $this->newDelivery();
    $delivery->markFailed(500, 'boom', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

    $this->expectException(InvalidArgumentException::class);

    $delivery->markFailed(500, 'again', new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
  }

  #[Test]
  public function itReconstitutesFromPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $deliveredAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    $delivery = WebhookDelivery::reconstitute(
      id: WebhookDeliveryId::fromString(self::DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'inspection.closed',
      eventId: 'event-9',
      payload: ['k' => 'v'],
      status: WebhookDeliveryStatus::DELIVERED,
      attempts: 2,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      httpStatus: 200,
      lastError: null,
      nextRetryAt: null,
      deliveredAt: $deliveredAt,
    );

    self::assertSame(WebhookDeliveryStatus::DELIVERED, $delivery->status());
    self::assertSame(2, $delivery->attempts());
    self::assertSame(200, $delivery->httpStatus());
    self::assertSame($createdAt, $delivery->createdAt());
    self::assertSame($updatedAt, $delivery->updatedAt());
    self::assertSame($deliveredAt, $delivery->deliveredAt());
  }

  /**
   * Method newDelivery.
   *
   * @return WebhookDelivery a fresh pending delivery under test
   */
  private function newDelivery(): WebhookDelivery
  {
    return WebhookDelivery::create(
      id: WebhookDeliveryId::fromString(self::DELIVERY_ID),
      subscriptionId: WebhookSubscriptionId::fromString(self::SUBSCRIPTION_ID),
      organizationId: self::ORGANIZATION_ID,
      eventType: 'intervention.published',
      eventId: 'event-1',
      payload: ['foo' => 'bar'],
    );
  }
}
