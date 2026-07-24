<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Contract\Event;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Contract\Event\WebhookEventType;

use function in_array;

/**
 * Test WebhookEventType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookEventType::class)]
final class WebhookEventTypeTest extends TestCase
{
  #[Test]
  public function valuesReturnsEveryBackingValue(): void
  {
    $values = WebhookEventType::values();

    self::assertCount(13, $values);
    self::assertContains('intervention.published', $values);
    self::assertContains('webhook.ping', $values);
  }

  #[Test]
  public function everyCaseValueIsPresent(): void
  {
    $values = WebhookEventType::values();

    foreach (WebhookEventType::cases() as $case) {
      self::assertTrue(in_array($case->value, $values, true));
    }
  }

  #[Test]
  public function theReservedPingValueIsStable(): void
  {
    self::assertSame('webhook.ping', WebhookEventType::PING->value);
  }
}
