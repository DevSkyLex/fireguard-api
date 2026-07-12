<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\Model\Equipment;

use DateTimeImmutable;
use Equipment\Domain\Exception\EquipmentAlreadyDecommissionedException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(Equipment::class)]
final class EquipmentTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655449002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655449003';

  #[Test]
  public function testDecommissionedEquipmentCannotBeUnassigned(): void
  {
    $equipment = $this->assignedEquipment();
    $equipment->commission();
    $equipment->decommission();

    self::assertSame(EquipmentStatus::DECOMMISSIONED, $equipment->status());

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $equipment->unassignFromFacility();
  }

  #[Test]
  public function testDecommissionedEquipmentCannotBeAssignedToFacility(): void
  {
    // Never assigned, so the terminal guard (not the "already assigned" guard) fires.
    $equipment = $this->newEquipment();
    $equipment->decommission();

    self::assertNull($equipment->facilityId());

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $equipment->assignToFacility(EquipmentFacilityId::fromString(self::FACILITY_ID), new DateTimeImmutable());
  }

  #[Test]
  public function testUnassigningOperationalEquipmentResetsItToInStock(): void
  {
    $equipment = $this->assignedEquipment();
    $equipment->commission();

    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());

    $equipment->unassignFromFacility();

    self::assertSame(EquipmentStatus::IN_STOCK, $equipment->status());
    self::assertNull($equipment->facilityId());
  }

  #[Test]
  public function testInStockEquipmentCannotBePutUnderMaintenance(): void
  {
    $equipment = $this->assignedEquipment();

    self::assertSame(EquipmentStatus::IN_STOCK, $equipment->status());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('In-stock equipment must be commissioned before it can be put under maintenance.');

    $equipment->putUnderMaintenance();
  }

  #[Test]
  public function testOperationalEquipmentCanBePutUnderMaintenance(): void
  {
    $equipment = $this->assignedEquipment();
    $equipment->commission();

    $equipment->putUnderMaintenance();

    self::assertSame(EquipmentStatus::UNDER_MAINTENANCE, $equipment->status());
  }

  private function newEquipment(): Equipment
  {
    return Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
  }

  private function assignedEquipment(): Equipment
  {
    $equipment = $this->newEquipment();
    $equipment->assignToFacility(EquipmentFacilityId::fromString(self::FACILITY_ID), new DateTimeImmutable());

    return $equipment;
  }
}
