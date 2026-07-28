<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Presentation\Api\Provider;

use ApiPlatform\Metadata\GetCollection;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Webhook\Application\Contract\Event\{WebhookEventCatalog, WebhookEventType};
use Webhook\Presentation\Api\Provider\WebhookEventTypeProvider;

use function array_column;
use function count;

/**
 * Test WebhookEventTypeProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(WebhookEventTypeProvider::class)]
final class WebhookEventTypeProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsOneRowPerAllowedEventType(): void
  {
    $rows = new WebhookEventTypeProvider()->provide(new GetCollection());

    $allowed = WebhookEventCatalog::allowedEventTypes();

    self::assertCount(count($allowed), $rows);
    self::assertSame($allowed, array_column($rows, 'value'));
  }

  #[Test]
  public function testProvideExcludesThePingEventType(): void
  {
    $rows = new WebhookEventTypeProvider()->provide(new GetCollection());

    self::assertNotContains(WebhookEventType::PING->value, array_column($rows, 'value'));
  }

  #[Test]
  public function testProvideHumanizesTheEventTypeAsTheLabel(): void
  {
    $rows = new WebhookEventTypeProvider()->provide(new GetCollection());

    self::assertNotEmpty($rows);

    foreach ($rows as $row) {
      self::assertNotSame('', $row->label);
      self::assertStringNotContainsString('.', $row->label);
      self::assertStringNotContainsString('_', $row->label);
    }
  }
}
