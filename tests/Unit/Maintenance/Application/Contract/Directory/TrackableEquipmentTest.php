<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Contract\Directory;

use Maintenance\Application\Contract\Directory\TrackableEquipment;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test TrackableEquipment.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TrackableEquipment::class)]
final class TrackableEquipmentTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $equipment = new TrackableEquipment('equip-1', 'org-1', 'facility-1', 'fire_extinguisher', 'active');

    self::assertSame('equip-1', $equipment->equipmentId);
    self::assertSame('org-1', $equipment->organizationId);
    self::assertSame('facility-1', $equipment->facilityId);
    self::assertSame('fire_extinguisher', $equipment->equipmentType);
    self::assertSame('active', $equipment->status);
  }

  #[Test]
  public function testAllowsAnUnassignedFacility(): void
  {
    $equipment = new TrackableEquipment('equip-1', 'org-1', null, 'alarm', 'active');

    self::assertNull($equipment->facilityId);
  }
}
