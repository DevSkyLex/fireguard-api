<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Event\Equipment;

use Equipment\Domain\Event\Equipment\EquipmentPutUnderMaintenanceEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentPutUnderMaintenanceEventTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentPutUnderMaintenanceEvent::class)]
final class EquipmentPutUnderMaintenanceEventTest extends TestCase
{
  #[Test]
  public function itExposesPayload(): void
  {
    $event = new EquipmentPutUnderMaintenanceEvent('org-1', 'equip-1', 'fac-1', 'operational');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('equip-1', $event->equipmentId);
    self::assertSame('fac-1', $event->facilityId);
    self::assertSame('operational', $event->previousStatus);
    self::assertNotSame('', $event->occurredAt->format('Y'));
  }

  #[Test]
  public function itAllowsNullFacility(): void
  {
    $event = new EquipmentPutUnderMaintenanceEvent('org-1', 'equip-1', null, 'in_stock');

    self::assertNull($event->facilityId);
  }
}
