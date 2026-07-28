<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Factory;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\UseCase\Query\Delivery\ListWebhookDeliveries\WebhookDeliveryResult;
use Webhook\Presentation\Api\Factory\WebhookDeliveryOutputFactory;

/**
 * Test WebhookDeliveryOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryOutputFactory::class)]
final class WebhookDeliveryOutputFactoryTest extends TestCase
{
  private const string DELIVERY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a30';

  private const string SUBSCRIPTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a31';

  #[Test]
  public function testFromViewMapsEveryFieldAndFormatsDates(): void
  {
    $view = new WebhookDeliveryResult(
      id: self::DELIVERY_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      eventType: 'intervention.published',
      status: 'delivered',
      attempts: 2,
      httpStatus: 204,
      lastError: 'previous failure',
      nextRetryAt: new DateTimeImmutable('2026-07-18T01:00:00+00:00'),
      deliveredAt: new DateTimeImmutable('2026-07-18T02:00:00+00:00'),
      createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
    );

    $output = new WebhookDeliveryOutputFactory()->fromView($view);

    self::assertSame(self::DELIVERY_ID, $output->id);
    self::assertSame(self::SUBSCRIPTION_ID, $output->subscriptionId);
    self::assertSame('intervention.published', $output->eventType);
    self::assertSame('delivered', $output->status);
    self::assertSame(2, $output->attempts);
    self::assertSame(204, $output->httpStatus);
    self::assertSame('previous failure', $output->lastError);
    self::assertSame('2026-07-18T01:00:00+00:00', $output->nextRetryAt);
    self::assertSame('2026-07-18T02:00:00+00:00', $output->deliveredAt);
    self::assertSame('2026-07-18T00:00:00+00:00', $output->createdAt);
  }

  #[Test]
  public function testFromViewLeavesOptionalTimestampsNull(): void
  {
    $view = new WebhookDeliveryResult(
      id: self::DELIVERY_ID,
      subscriptionId: self::SUBSCRIPTION_ID,
      eventType: 'webhook.ping',
      status: 'queued',
      attempts: 0,
      httpStatus: null,
      lastError: null,
      nextRetryAt: null,
      deliveredAt: null,
      createdAt: new DateTimeImmutable('2026-07-18T00:00:00+00:00'),
    );

    $output = new WebhookDeliveryOutputFactory()->fromView($view);

    self::assertNull($output->httpStatus);
    self::assertNull($output->lastError);
    self::assertNull($output->nextRetryAt);
    self::assertNull($output->deliveredAt);
    self::assertSame(0, $output->attempts);
  }
}
