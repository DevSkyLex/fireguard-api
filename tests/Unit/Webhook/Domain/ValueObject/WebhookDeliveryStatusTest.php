<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Domain\ValueObject\WebhookDeliveryStatus;

/**
 * Test WebhookDeliveryStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookDeliveryStatus::class)]
final class WebhookDeliveryStatusTest extends TestCase
{
  #[Test]
  public function itExposesEveryBackingValue(): void
  {
    self::assertSame(['pending', 'delivered', 'failed'], WebhookDeliveryStatus::values());
  }

  #[Test]
  public function pendingIsNotTerminal(): void
  {
    self::assertFalse(WebhookDeliveryStatus::PENDING->isTerminal());
  }

  #[Test]
  public function deliveredAndFailedAreTerminal(): void
  {
    self::assertTrue(WebhookDeliveryStatus::DELIVERED->isTerminal());
    self::assertTrue(WebhookDeliveryStatus::FAILED->isTerminal());
  }
}
