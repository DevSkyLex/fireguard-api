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

use function str_repeat;

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

  #[Test]
  public function testCreateNormalizesOptionalStringsAndExposesInitialState(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SMOKE_DETECTOR,
      subType: '  optical  ',
      brand: '  Acme  ',
      model: '  X-100  ',
      serialNumber: '  SN-42  ',
      locationLabel: '  Hallway B  ',
    );

    self::assertSame(self::EQUIP_ID, (string) $equipment->id());
    self::assertSame(self::ORG_ID, (string) $equipment->organizationId());
    self::assertSame(EquipmentType::SMOKE_DETECTOR, $equipment->type());
    self::assertSame(EquipmentStatus::IN_STOCK, $equipment->status());
    self::assertNull($equipment->facilityId());
    self::assertNull($equipment->installedAt());
    self::assertNull($equipment->commissionedAt());
    self::assertSame('optical', $equipment->subType());
    self::assertSame('Acme', $equipment->brand());
    self::assertSame('X-100', $equipment->model());
    self::assertSame('SN-42', $equipment->serialNumber());
    self::assertSame('Hallway B', $equipment->locationLabel());
    self::assertSame($equipment->createdAt(), $equipment->updatedAt());
  }

  #[Test]
  public function testCreateTreatsWhitespaceOnlyValuesAsNull(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::OTHER,
      subType: '   ',
    );

    self::assertNull($equipment->subType());
    self::assertNull($equipment->brand());
  }

  #[Test]
  public function testCreateRejectsSubTypeExceedingMaxLength(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment sub-type must be at most 100 characters.');

    Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::OTHER,
      subType: str_repeat('a', 101),
    );
  }

  #[Test]
  public function testCreateRejectsLocationLabelExceedingMaxLength(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment location label must be at most 255 characters.');

    Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::OTHER,
      locationLabel: str_repeat('b', 256),
    );
  }

  #[Test]
  public function testReconstituteRestoresPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01 08:00:00');
    $updatedAt = new DateTimeImmutable('2026-02-01 09:00:00');
    $installedAt = new DateTimeImmutable('2026-01-15 10:00:00');
    $commissionedAt = new DateTimeImmutable('2026-01-20 11:00:00');

    $equipment = Equipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SPRINKLER,
      status: EquipmentStatus::OPERATIONAL,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      facilityId: EquipmentFacilityId::fromString(self::FACILITY_ID),
      subType: 'wet-pipe',
      brand: 'Tyco',
      model: 'TY-B',
      serialNumber: 'SN-777',
      locationLabel: 'Ceiling',
      installedAt: $installedAt,
      commissionedAt: $commissionedAt,
    );

    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());
    self::assertSame(self::FACILITY_ID, (string) $equipment->facilityId());
    self::assertSame('wet-pipe', $equipment->subType());
    self::assertSame('Tyco', $equipment->brand());
    self::assertSame('TY-B', $equipment->model());
    self::assertSame('SN-777', $equipment->serialNumber());
    self::assertSame('Ceiling', $equipment->locationLabel());
    self::assertSame($createdAt, $equipment->createdAt());
    self::assertSame($updatedAt, $equipment->updatedAt());
    self::assertSame($installedAt, $equipment->installedAt());
    self::assertSame($commissionedAt, $equipment->commissionedAt());
  }

  #[Test]
  public function testUpdateReplacesMutableFieldsAndTouchesTimestamp(): void
  {
    $equipment = $this->newEquipment();

    $equipment->update(
      type: EquipmentType::HYDRANT,
      subType: '  underground  ',
      brand: '  Bavaria  ',
      model: '  H-9  ',
      serialNumber: '  SN-9  ',
      locationLabel: '  Yard  ',
    );

    self::assertSame(EquipmentType::HYDRANT, $equipment->type());
    self::assertSame('underground', $equipment->subType());
    self::assertSame('Bavaria', $equipment->brand());
    self::assertSame('H-9', $equipment->model());
    self::assertSame('SN-9', $equipment->serialNumber());
    self::assertSame('Yard', $equipment->locationLabel());
    self::assertGreaterThanOrEqual($equipment->createdAt(), $equipment->updatedAt());
  }

  #[Test]
  public function testAssignToFacilitySetsFacilityAndInstalledAt(): void
  {
    $equipment = $this->newEquipment();
    $installedAt = new DateTimeImmutable('2026-03-01 12:00:00');

    $equipment->assignToFacility(EquipmentFacilityId::fromString(self::FACILITY_ID), $installedAt);

    self::assertSame(self::FACILITY_ID, (string) $equipment->facilityId());
    self::assertSame($installedAt, $equipment->installedAt());
  }

  #[Test]
  public function testAssigningAlreadyAssignedEquipmentThrows(): void
  {
    $equipment = $this->assignedEquipment();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment is already assigned to a facility. Unassign it first.');

    $equipment->assignToFacility(EquipmentFacilityId::fromString(self::FACILITY_ID), new DateTimeImmutable());
  }

  #[Test]
  public function testUnassignFromFacilityClearsInstallationDetails(): void
  {
    $equipment = $this->assignedEquipment();

    self::assertNotNull($equipment->installedAt());

    $equipment->unassignFromFacility();

    self::assertNull($equipment->facilityId());
    self::assertNull($equipment->installedAt());
    self::assertSame(EquipmentStatus::IN_STOCK, $equipment->status());
  }

  #[Test]
  public function testCommissionRequiresFacilityAssignment(): void
  {
    $equipment = $this->newEquipment();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment must be assigned to a facility before commissioning.');

    $equipment->commission();
  }

  #[Test]
  public function testCommissionSetsOperationalAndRecordsCommissionedAt(): void
  {
    $equipment = $this->assignedEquipment();

    self::assertNull($equipment->commissionedAt());

    $equipment->commission();

    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());
    self::assertNotNull($equipment->commissionedAt());
  }

  #[Test]
  public function testRecommissionPreservesOriginalCommissionedAt(): void
  {
    $equipment = $this->assignedEquipment();
    $equipment->commission();
    $firstCommissionedAt = $equipment->commissionedAt();

    $equipment->putUnderMaintenance();
    $equipment->commission();

    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());
    self::assertSame($firstCommissionedAt, $equipment->commissionedAt());
  }

  #[Test]
  public function testDecommissionedEquipmentCannotBeCommissioned(): void
  {
    $equipment = $this->newEquipment();
    $equipment->decommission();

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $equipment->commission();
  }

  #[Test]
  public function testDecommissionedEquipmentCannotBePutUnderMaintenance(): void
  {
    $equipment = $this->newEquipment();
    $equipment->decommission();

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $equipment->putUnderMaintenance();
  }

  #[Test]
  public function testDecommissioningAlreadyDecommissionedEquipmentThrows(): void
  {
    $equipment = $this->newEquipment();
    $equipment->decommission();

    self::assertSame(EquipmentStatus::DECOMMISSIONED, $equipment->status());

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $equipment->decommission();
  }

  #[Test]
  public function testPutUnderMaintenanceRequiresFacilityAssignment(): void
  {
    // Reconstitute an operational-but-unassigned state, unreachable through the
    // normal lifecycle, to exercise the missing-facility guard.
    $equipment = Equipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_ALARM_PANEL,
      status: EquipmentStatus::OPERATIONAL,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
      facilityId: null,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment must be assigned to a facility before putting it under maintenance.');

    $equipment->putUnderMaintenance();
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
