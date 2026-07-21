<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\ListInspections;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, EquipmentNamingPort, FacilityNamingPort};
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\{ListInspectionsHandler, ListInspectionsQuery};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector
};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

#[CoversClass(ListInspectionsHandler::class)]
final class ListInspectionsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655449002';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655449003';

  #[Test]
  public function testInvokePassesFiltersPaginationAndSortingToRepository(): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      inspector: Inspector::forUser('550e8400-e29b-41d4-a716-446655449004', 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        InspectionOrganizationId::fromString(self::ORG_ID),
        self::EQUIPMENT_ID,
        '550e8400-e29b-41d4-a716-446655449005',
        'pass',
        'draft',
        '2026-01-01T00:00:00+00:00',
        '2026-01-31T23:59:59+00:00',
        '550e8400-e29b-41d4-a716-446655449004',
        null,
        '550e8400-e29b-41d4-a716-446655449006',
        'john',
        new Sorting('performedAt', SortDirection::DESC),
        15,
        30,
      )
      ->willReturn([$inspection]);
    $inspectionRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        InspectionOrganizationId::fromString(self::ORG_ID),
        self::EQUIPMENT_ID,
        '550e8400-e29b-41d4-a716-446655449005',
        'pass',
        'draft',
        '2026-01-01T00:00:00+00:00',
        '2026-01-31T23:59:59+00:00',
        '550e8400-e29b-41d4-a716-446655449004',
        null,
        '550e8400-e29b-41d4-a716-446655449006',
        'john',
      )
      ->willReturn(4);

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())
      ->method('countsByInspectionIds')
      ->with([self::INSPECTION_ID])
      ->willReturn([self::INSPECTION_ID => 2]);

    $handler = new ListInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
    );

    $result = $handler->__invoke(new ListInspectionsQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIPMENT_ID,
      facilityId: '550e8400-e29b-41d4-a716-446655449005',
      result: 'pass',
      status: 'draft',
      performedAtFrom: '2026-01-01T00:00:00+00:00',
      performedAtTo: '2026-01-31T23:59:59+00:00',
      inspectorUserId: '550e8400-e29b-41d4-a716-446655449004',
      checklistId: '550e8400-e29b-41d4-a716-446655449006',
      pagination: new Pagination(offset: 30, limit: 15),
      search: 'john',
      sorting: new Sorting('performedAt', SortDirection::DESC),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertInstanceOf(GetInspectionResult::class, $result->items[0]);
    self::assertSame(self::INSPECTION_ID, $result->items[0]->inspectionId);
    self::assertSame(2, $result->items[0]->nonConformitiesCount);
    self::assertSame(4, $result->total);
    self::assertSame(15, $result->limit);
    self::assertSame(30, $result->offset);
  }

  #[Test]
  public function testInvokeReturnsEmptyPaginatedResultWhenNoInspections(): void
  {
    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())->method('findByOrganizationId')->willReturn([]);
    $inspectionRepository->expects(self::once())->method('countByOrganizationId')->willReturn(0);

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())->method('countsByInspectionIds')->with([])->willReturn([]);

    $handler = new ListInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
    );

    $result = $handler->__invoke(new ListInspectionsQuery(
      organizationId: self::ORG_ID,
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertEmpty($result->items);
    self::assertSame(0, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeThrowsWhenPerformedAtRangeIsInvalid(): void
  {
    $handler = new ListInspectionsHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListInspectionsQuery(
      organizationId: self::ORG_ID,
      performedAtFrom: '2026-02-01T00:00:00+00:00',
      performedAtTo: '2026-01-01T00:00:00+00:00',
    ));
  }
}
