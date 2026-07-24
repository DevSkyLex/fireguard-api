<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Contract\Event;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Contract\Event\{WebhookEventCatalog, WebhookEventType};

/**
 * Test WebhookEventCatalog.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookEventCatalog::class)]
final class WebhookEventCatalogTest extends TestCase
{
  #[Test]
  public function allowedEventTypesExcludesThePingType(): void
  {
    $allowed = WebhookEventCatalog::allowedEventTypes();

    self::assertNotContains(WebhookEventType::PING->value, $allowed);
    self::assertContains('intervention.published', $allowed);
    self::assertCount(12, $allowed);
  }

  #[Test]
  public function isAllowedEventTypeAcceptsACuratedType(): void
  {
    self::assertTrue(WebhookEventCatalog::isAllowedEventType('intervention.published'));
  }

  #[Test]
  public function isAllowedEventTypeRejectsThePingType(): void
  {
    self::assertFalse(WebhookEventCatalog::isAllowedEventType(WebhookEventType::PING->value));
  }

  #[Test]
  public function isAllowedEventTypeRejectsAnUnknownType(): void
  {
    self::assertFalse(WebhookEventCatalog::isAllowedEventType('auth.user_logged_in_event'));
  }
}
