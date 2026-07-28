<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{
  EquipmentFacilityId,
  EquipmentId,
  EquipmentOrganizationId,
  EquipmentStatus,
  EquipmentType
};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\EquipmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use LogicException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentMapperTest.
 *
 * @category Mapper Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentMapper::class)]
final class EquipmentMapperTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655493001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655493002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655493003';

  #[Test]
  public function testToDomainMapsEveryColumn(): void
  {
    $record = $this->record();

    $equipment = EquipmentMapper::toDomain($record);

    self::assertSame(self::EQUIPMENT_ID, (string) $equipment->id());
    self::assertSame(self::ORGANIZATION_ID, (string) $equipment->organizationId());
    self::assertSame(self::FACILITY_ID, (string) $equipment->facilityId());
    self::assertSame(EquipmentType::FIRE_EXTINGUISHER, $equipment->type());
    self::assertSame(EquipmentStatus::OPERATIONAL, $equipment->status());
    self::assertSame('CO2', $equipment->subType());
    self::assertSame('Sicli', $equipment->brand());
    self::assertSame('ABC-9', $equipment->model());
    self::assertSame('EXT-001', $equipment->serialNumber());
    self::assertSame('Hall', $equipment->locationLabel());
    self::assertEquals(new DateTimeImmutable('2026-01-05T00:00:00+00:00'), $equipment->installedAt());
    self::assertEquals(new DateTimeImmutable('2026-01-06T00:00:00+00:00'), $equipment->commissionedAt());
  }

  #[Test]
  public function testToDomainLeavesTheFacilityUnassignedWhenTheColumnIsNull(): void
  {
    $record = $this->record();
    $record->facilityId = null;
    $record->subType = null;
    $record->installedAt = null;
    $record->commissionedAt = null;

    $equipment = EquipmentMapper::toDomain($record);

    self::assertNull($equipment->facilityId());
    self::assertNull($equipment->subType());
    self::assertNull($equipment->installedAt());
    self::assertNull($equipment->commissionedAt());
  }

  #[Test]
  public function testToDomainRefusesARecordWithoutAnOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Equipment record must reference an organization.');

    EquipmentMapper::toDomain($record);
  }

  #[Test]
  public function testToRecordMapsEveryColumn(): void
  {
    $equipment = EquipmentMapper::toDomain($this->record());

    $record = EquipmentMapper::toRecord($equipment);

    self::assertSame(self::EQUIPMENT_ID, $record->id);
    self::assertSame(self::FACILITY_ID, $record->facilityId);
    self::assertSame('fire_extinguisher', $record->type);
    self::assertSame('CO2', $record->subType);
    self::assertSame('Sicli', $record->brand);
    self::assertSame('ABC-9', $record->model);
    self::assertSame('EXT-001', $record->serialNumber);
    self::assertSame('Hall', $record->locationLabel);
    self::assertSame('operational', $record->status);
  }

  #[Test]
  public function testToRecordDoesNotCarryTheOrganizationAssociation(): void
  {
    // The association is owned by the repository (it resolves the managed
    // OrganizationRecord); the mapper must leave it untouched.
    $record = EquipmentMapper::toRecord(EquipmentMapper::toDomain($this->record()));

    self::assertNull($record->organization);
  }

  #[Test]
  public function testToRecordWritesANullFacilityIdForUnassignedEquipment(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $equipment = Equipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      type: EquipmentType::SMOKE_DETECTOR,
      status: EquipmentStatus::IN_STOCK,
      createdAt: $now,
      updatedAt: $now,
    );

    $record = EquipmentMapper::toRecord($equipment);

    self::assertNull($record->facilityId);
    self::assertSame('smoke_detector', $record->type);
    self::assertSame('in_stock', $record->status);
    self::assertEquals($now, $record->createdAt);
    self::assertEquals($now, $record->updatedAt);
  }

  #[Test]
  public function testARoundTripPreservesTheFacilityAssignment(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $equipment = Equipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      type: EquipmentType::HYDRANT,
      status: EquipmentStatus::DECOMMISSIONED,
      createdAt: $now,
      updatedAt: $now,
      facilityId: EquipmentFacilityId::fromString(self::FACILITY_ID),
    );

    $record = EquipmentMapper::toRecord($equipment);
    $record->organization = $this->organization();

    $roundTripped = EquipmentMapper::toDomain($record);

    self::assertSame(self::FACILITY_ID, (string) $roundTripped->facilityId());
    self::assertSame(EquipmentType::HYDRANT, $roundTripped->type());
    self::assertSame(EquipmentStatus::DECOMMISSIONED, $roundTripped->status());
  }

  private function organization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    return $organization;
  }

  private function record(): EquipmentRecord
  {
    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $this->organization();
    $record->facilityId = self::FACILITY_ID;
    $record->type = 'fire_extinguisher';
    $record->subType = 'CO2';
    $record->brand = 'Sicli';
    $record->model = 'ABC-9';
    $record->serialNumber = 'EXT-001';
    $record->locationLabel = 'Hall';
    $record->status = 'operational';
    $record->installedAt = new DateTimeImmutable('2026-01-05T00:00:00+00:00');
    $record->commissionedAt = new DateTimeImmutable('2026-01-06T00:00:00+00:00');
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');

    return $record;
  }
}
