<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Inspection;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{
  EquipmentFacilityId,
  EquipmentId,
  EquipmentOrganizationId,
  EquipmentStatus,
  EquipmentType
};
use Equipment\Infrastructure\Adapter\Inspection\EquipmentValidationAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(EquipmentValidationAdapter::class)]
final class EquipmentValidationAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string OTHER_FACILITY_ID = '550e8400-e29b-41d4-a716-446655440004';

  // #region Methods
  #[Test]
  public function testAssertEquipmentIsInspectablePassesForOperationalEquipmentAtMatchingFacility(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::OPERATIONAL, self::FACILITY_ID),
    ));

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, self::ORG_ID, self::FACILITY_ID);

    $this->expectNotToPerformAssertions();
  }

  #[Test]
  public function testAssertEquipmentIsInspectablePassesWhenNoFacilityGiven(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::IN_STOCK, null),
    ));

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, self::ORG_ID, null);

    $this->expectNotToPerformAssertions();
  }

  #[Test]
  public function testAssertEquipmentIsInspectableThrowsWhenEquipmentNotFound(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(null));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not found');

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, self::ORG_ID, null);
  }

  #[Test]
  public function testAssertEquipmentIsInspectableThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::OPERATIONAL, self::FACILITY_ID),
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not found');

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, '550e8400-e29b-41d4-a716-446655440099', null);
  }

  #[Test]
  public function testAssertEquipmentIsInspectableThrowsWhenEquipmentDecommissioned(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::DECOMMISSIONED, self::FACILITY_ID),
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('decommissioned');

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, self::ORG_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testAssertEquipmentIsInspectableThrowsWhenEquipmentUnassignedButFacilityGiven(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::IN_STOCK, null),
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not assigned to facility');

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, self::ORG_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testAssertEquipmentIsInspectableThrowsWhenEquipmentAssignedToDifferentFacility(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::OPERATIONAL, self::OTHER_FACILITY_ID),
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not assigned to facility');

    $adapter->assertEquipmentIsInspectable(self::EQUIP_ID, self::ORG_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testAssertEquipmentExistsPassesForEquipmentInTheSameOrganization(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::DECOMMISSIONED, null),
    ));

    // Existence alone is asserted here: a decommissioned item still exists.
    $adapter->assertEquipmentExists(self::EQUIP_ID, self::ORG_ID);

    $this->expectNotToPerformAssertions();
  }

  #[Test]
  public function testAssertEquipmentExistsThrowsWhenEquipmentIsMissing(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(null));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not found');

    $adapter->assertEquipmentExists(self::EQUIP_ID, self::ORG_ID);
  }

  #[Test]
  public function testAssertEquipmentExistsThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $adapter = new EquipmentValidationAdapter($this->repositoryReturning(
      $this->equipment(EquipmentStatus::OPERATIONAL, null),
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not found');

    $adapter->assertEquipmentExists(self::EQUIP_ID, '550e8400-e29b-41d4-a716-446655440099');
  }

  /**
   * Builds an equipment aggregate in a specific lifecycle state.
   */
  private function equipment(EquipmentStatus $status, ?string $facilityId): Equipment
  {
    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    return Equipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
      status: $status,
      createdAt: $now,
      updatedAt: $now,
      facilityId: null !== $facilityId ? EquipmentFacilityId::fromString($facilityId) : null,
    );
  }

  /**
   * Builds a repository stub returning the given equipment (or null).
   */
  private function repositoryReturning(?Equipment $equipment): EquipmentRepositoryPort
  {
    $repository = $this->createStub(EquipmentRepositoryPort::class);
    $repository->method('findById')->willReturn($equipment);

    return $repository;
  }
  // #endregion
}
