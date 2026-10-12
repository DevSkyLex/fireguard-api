<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Contract\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Contract\Event\WebhookEventType;

/**
 * Class WebhookEventTypeTest
 *
 * @category Test
 */
#[CoversClass(WebhookEventType::class)]
final class WebhookEventTypeTest extends TestCase
{
  #[Test]
  public function retainsStableLegacyLiterals(): void
  {
    self::assertSame('webhook.ping', WebhookEventType::PING);
    self::assertSame('intervention.published', WebhookEventType::INTERVENTION_PUBLISHED);
    self::assertSame('facility.updated', WebhookEventType::FACILITY_UPDATED);
  }

  #[Test]
  public function representsHistoricalAndAdditionalValuesWithoutLoadedContributors(): void
  {
    self::assertSame('invoice.issued', new WebhookEventType('invoice.issued')->value);
    self::assertSame('removed.type', new WebhookEventType('removed.type')->value);
  }

  #[Test]
  public function rejectsInvalidRepresentations(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new WebhookEventType(' Invalid Type ');
  }
}
