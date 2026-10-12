<?php

declare(strict_types=1);

namespace Tests\Unit\Webhook\Application\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tests\Support\Catalogs\BuiltInCapabilityCatalogs;
use Webhook\Application\Contract\Event\{WebhookEventDescriptor, WebhookEventType};
use Webhook\Application\Port\Outbound\WebhookEventSourcePort;
use Webhook\Application\Service\WebhookEventCatalogRegistry;

/**
 * Class WebhookEventCatalogRegistryTest
 *
 * @category Test
 */
#[CoversClass(WebhookEventCatalogRegistry::class)]
final class WebhookEventCatalogRegistryTest extends TestCase
{
  #[Test]
  public function exposesAnAdditionalContributedTypeAndDeniesUnknownSelection(): void
  {
    $descriptor = new WebhookEventDescriptor('invoice.issued', 'Invoice issued', 'invoice.invoice_issued_event');
    $source = $this->createStub(WebhookEventSourcePort::class);
    $source->method('descriptors')->willReturn([$descriptor]);
    $catalog = new WebhookEventCatalogRegistry([$source]);

    self::assertSame([$descriptor], $catalog->descriptors());
    self::assertTrue($catalog->isAllowedEventType('invoice.issued'));
    self::assertFalse($catalog->isAllowedEventType('missing.type'));
    self::assertFalse($catalog->isAllowedEventType(WebhookEventType::PING));
  }

  #[Test]
  public function rejectsDuplicatePublicKeysAtConstruction(): void
  {
    $source = $this->createStub(WebhookEventSourcePort::class);
    $source->method('descriptors')->willReturn([new WebhookEventDescriptor('invoice.issued', 'Invoice issued', 'invoice.issued')]);

    $this->expectException(InvalidArgumentException::class);
    new WebhookEventCatalogRegistry([$source, $source]);
  }

  #[Test]
  public function rejectsReservedPingContributions(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new WebhookEventDescriptor(WebhookEventType::PING, 'Ping', 'invoice.ping');
  }

  #[Test]
  public function preservesAllLegacyTypesWithoutRequiringContributorsForRepresentation(): void
  {
    $catalog = BuiltInCapabilityCatalogs::webhook();
    self::assertCount(14, $catalog->descriptors());
    self::assertTrue($catalog->isAllowedEventType('inspection.non_conformity_recorded'));
    self::assertTrue($catalog->isAllowedEventType('facility.updated'));
    self::assertSame('removed.event', new WebhookEventType('removed.event')->value);
    self::assertFalse(new WebhookEventCatalogRegistry([])->isAllowedEventType('removed.event'));
  }
}
