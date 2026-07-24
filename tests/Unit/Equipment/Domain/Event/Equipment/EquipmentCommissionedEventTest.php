<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Event\Equipment;

use Equipment\Domain\Event\Equipment\EquipmentCommissionedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentCommissionedEventTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentCommissionedEvent::class)]
final class EquipmentCommissionedEventTest extends TestCase
{
  #[Test]
  public function itExposesPayload(): void
  {
    $event = new EquipmentCommissionedEvent('org-1', 'equip-1', 'fac-1', 'in_stock');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('equip-1', $event->equipmentId);
    self::assertSame('fac-1', $event->facilityId);
    self::assertSame('in_stock', $event->previousStatus);
    self::assertNotSame('', $event->occurredAt->format('Y'));
  }

  #[Test]
  public function itAllowsNullFacility(): void
  {
    $event = new EquipmentCommissionedEvent('org-1', 'equip-1', null, 'under_maintenance');

    self::assertNull($event->facilityId);
  }
}
