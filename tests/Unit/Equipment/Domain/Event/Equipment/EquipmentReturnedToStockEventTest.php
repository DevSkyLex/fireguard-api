<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Event\Equipment;

use Equipment\Domain\Event\Equipment\EquipmentReturnedToStockEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentReturnedToStockEventTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentReturnedToStockEvent::class)]
final class EquipmentReturnedToStockEventTest extends TestCase
{
  #[Test]
  public function itExposesPayload(): void
  {
    $event = new EquipmentReturnedToStockEvent('org-1', 'equip-1', 'operational');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('equip-1', $event->equipmentId);
    self::assertSame('operational', $event->previousStatus);
    self::assertNotSame('', $event->occurredAt->format('Y'));
  }
}
