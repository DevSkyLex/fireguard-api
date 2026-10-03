<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary;

use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilitySubtreeScopePort};
use Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary\{GetFacilityEquipmentSummaryHandler, GetFacilityEquipmentSummaryQuery};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetFacilityEquipmentSummaryHandlerTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityEquipmentSummaryHandler::class)]
final class GetFacilityEquipmentSummaryHandlerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION = '960e8400-e29b-41d4-a716-446655470001';

  private const string FACILITY = '960e8400-e29b-41d4-a716-446655470002';
  // #endregion

  // #region Methods
  #[Test]
  public function subtreeCountsAllStatusesAndIncludesEmptyBuckets(): void
  {
    $facilities = $this->createMock(FacilitySubtreeScopePort::class);
    $facilities->expects(self::once())->method('findPublishedSubtreeIds')->with(self::ORGANIZATION, self::FACILITY)->willReturn([self::FACILITY, 'descendant']);
    $equipment = $this->createMock(EquipmentRepositoryPort::class);
    $equipment->expects(self::once())->method('countByStatusForCriteria')
      ->with(new EquipmentOrganizationId(self::ORGANIZATION), new EquipmentListCriteria(facilityIds: [self::FACILITY, 'descendant']))
      ->willReturn(['operational' => 205, 'under_maintenance' => 3, 'decommissioned' => 2]);
    $result = (new GetFacilityEquipmentSummaryHandler($equipment, $facilities))(new GetFacilityEquipmentSummaryQuery(self::ORGANIZATION, self::FACILITY));
    self::assertSame('subtree', $result->scope);
    self::assertSame(210, $result->totalItems);
    self::assertSame(['in_stock' => 0, 'operational' => 205, 'under_maintenance' => 3, 'decommissioned' => 2], $result->byStatus);
    self::assertSame(5, $result->needingAttentionCount);
  }

  #[Test]
  public function directCountsKeepCollectionScopeAndCheckTheFacility(): void
  {
    $facilities = $this->createMock(FacilitySubtreeScopePort::class);
    $facilities->expects(self::once())->method('findPublishedSubtreeIds')->willReturn([self::FACILITY, 'descendant']);
    $equipment = $this->createMock(EquipmentRepositoryPort::class);
    $equipment->expects(self::once())->method('countByStatusForCriteria')
      ->with(new EquipmentOrganizationId(self::ORGANIZATION), new EquipmentListCriteria(facilityId: self::FACILITY))
      ->willReturn([]);
    $result = (new GetFacilityEquipmentSummaryHandler($equipment, $facilities))(new GetFacilityEquipmentSummaryQuery(self::ORGANIZATION, self::FACILITY, false));
    self::assertSame('direct', $result->scope);
    self::assertSame(0, $result->totalItems);
    self::assertSame(0, $result->needingAttentionCount);
  }

  #[Test]
  public function unknownOrForeignFacilityDoesNotQueryEquipment(): void
  {
    $facilities = $this->createStub(FacilitySubtreeScopePort::class);
    $facilities->method('findPublishedSubtreeIds')->willReturn([]);
    $equipment = $this->createMock(EquipmentRepositoryPort::class);
    $equipment->expects(self::never())->method('countByStatusForCriteria');
    $this->expectException(EquipmentNotFoundException::class);
    (new GetFacilityEquipmentSummaryHandler($equipment, $facilities))(new GetFacilityEquipmentSummaryQuery(self::ORGANIZATION, self::FACILITY, false));
  }
  // #endregion
}
