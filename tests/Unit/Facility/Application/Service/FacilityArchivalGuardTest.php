<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityInspectionDependencyPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityArchivalGuard;
use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(FacilityArchivalGuard::class)]
final class FacilityArchivalGuardTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testRejectsWhenActiveChildFacilitiesExist(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('hasActiveDescendants')->willReturn(true);

    $equipment = $this->createMock(FacilityEquipmentDependencyPort::class);
    $equipment->expects(self::never())->method('hasActiveEquipmentInFacility');

    $inspection = $this->createMock(FacilityInspectionDependencyPort::class);
    $inspection->expects(self::never())->method('hasActiveInspectionInFacility');

    $this->expectException(FacilityHasActiveDependentsException::class);
    $this->expectExceptionMessage('active child facilities');

    new FacilityArchivalGuard($repository, $equipment, $inspection)
      ->assertNoActiveDependents(self::ORGANIZATION_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testRejectsWhenActiveEquipmentExists(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('hasActiveDescendants')->willReturn(false);

    $equipment = $this->createMock(FacilityEquipmentDependencyPort::class);
    $equipment->expects(self::once())
      ->method('hasActiveEquipmentInFacility')
      ->with(self::ORGANIZATION_ID, self::FACILITY_ID)
      ->willReturn(true);

    $inspection = $this->createMock(FacilityInspectionDependencyPort::class);
    $inspection->expects(self::never())->method('hasActiveInspectionInFacility');

    $this->expectException(FacilityHasActiveDependentsException::class);
    $this->expectExceptionMessage('active equipment');

    new FacilityArchivalGuard($repository, $equipment, $inspection)
      ->assertNoActiveDependents(self::ORGANIZATION_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testRejectsWhenActiveInspectionExists(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('hasActiveDescendants')->willReturn(false);

    $equipment = $this->createStub(FacilityEquipmentDependencyPort::class);
    $equipment->method('hasActiveEquipmentInFacility')->willReturn(false);

    $inspection = $this->createMock(FacilityInspectionDependencyPort::class);
    $inspection->expects(self::once())
      ->method('hasActiveInspectionInFacility')
      ->with(self::ORGANIZATION_ID, self::FACILITY_ID)
      ->willReturn(true);

    $this->expectException(FacilityHasActiveDependentsException::class);
    $this->expectExceptionMessage('in-progress inspections');

    new FacilityArchivalGuard($repository, $equipment, $inspection)
      ->assertNoActiveDependents(self::ORGANIZATION_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testPassesWhenNoActiveDependents(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('hasActiveDescendants')->willReturn(false);

    $equipment = $this->createStub(FacilityEquipmentDependencyPort::class);
    $equipment->method('hasActiveEquipmentInFacility')->willReturn(false);

    $inspection = $this->createStub(FacilityInspectionDependencyPort::class);
    $inspection->method('hasActiveInspectionInFacility')->willReturn(false);

    new FacilityArchivalGuard($repository, $equipment, $inspection)
      ->assertNoActiveDependents(self::ORGANIZATION_ID, self::FACILITY_ID);

    $this->addToAssertionCount(1);
  }
}
