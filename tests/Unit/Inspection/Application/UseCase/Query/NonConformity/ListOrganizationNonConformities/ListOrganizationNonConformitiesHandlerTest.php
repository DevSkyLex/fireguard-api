<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities;

use Inspection\Application\Port\Outbound\{EquipmentNamingPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities\{ListOrganizationNonConformitiesHandler, ListOrganizationNonConformitiesQuery, OrganizationNonConformityResult};
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, NonConformityId, NonConformityInspectionId, NonConformitySeverity};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

#[CoversClass(ListOrganizationNonConformitiesHandler::class)]
final class ListOrganizationNonConformitiesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testInvokePassesFiltersPaginationAndSortingAndResolvesEquipment(): void
  {
    $nonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Missing extinguisher',
      severity: NonConformitySeverity::HIGH,
    );

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        InspectionOrganizationId::fromString(self::ORG_ID),
        'high',
        'open',
        'fire',
        new Sorting('severity', SortDirection::ASC),
        15,
        30,
      )
      ->willReturn([$nonConformity]);
    $nonConformityRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        InspectionOrganizationId::fromString(self::ORG_ID),
        'high',
        'open',
        'fire',
      )
      ->willReturn(7);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findEquipmentIdsByIds')
      ->with([self::INSPECTION_ID])
      ->willReturn([self::INSPECTION_ID => self::EQUIPMENT_ID]);

    /** @var EquipmentNamingPort&MockObject $equipmentNaming */
    $equipmentNaming = $this->createMock(EquipmentNamingPort::class);
    $equipmentNaming->expects(self::once())
      ->method('findSerialNumbersByIds')
      ->with([self::EQUIPMENT_ID])
      ->willReturn([self::EQUIPMENT_ID => 'SN-001']);

    $handler = new ListOrganizationNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      inspectionRepository: $inspectionRepository,
      equipmentNaming: $equipmentNaming,
    );

    $result = $handler->__invoke(new ListOrganizationNonConformitiesQuery(
      organizationId: self::ORG_ID,
      severity: 'high',
      status: 'open',
      pagination: new Pagination(offset: 30, limit: 15),
      search: 'fire',
      sorting: new Sorting('severity', SortDirection::ASC),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertInstanceOf(OrganizationNonConformityResult::class, $result->items[0]);
    self::assertSame(self::NC_ID, $result->items[0]->nonConformityId);
    self::assertSame(self::INSPECTION_ID, $result->items[0]->inspectionId);
    self::assertSame(self::EQUIPMENT_ID, $result->items[0]->equipmentId);
    self::assertSame('SN-001', $result->items[0]->equipmentSerialNumber);
    self::assertSame(7, $result->total);
    self::assertSame(15, $result->limit);
    self::assertSame(30, $result->offset);
  }

  #[Test]
  public function testInvokeLeavesEquipmentNullWhenInspectionCannotBeResolved(): void
  {
    $nonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Missing extinguisher',
      severity: NonConformitySeverity::LOW,
    );

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())->method('findByOrganizationId')->willReturn([$nonConformity]);
    $nonConformityRepository->expects(self::once())->method('countByOrganizationId')->willReturn(1);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findEquipmentIdsByIds')
      ->with([self::INSPECTION_ID])
      ->willReturn([]);

    /** @var EquipmentNamingPort&MockObject $equipmentNaming */
    $equipmentNaming = $this->createMock(EquipmentNamingPort::class);
    $equipmentNaming->expects(self::once())
      ->method('findSerialNumbersByIds')
      ->with([])
      ->willReturn([]);

    $handler = new ListOrganizationNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      inspectionRepository: $inspectionRepository,
      equipmentNaming: $equipmentNaming,
    );

    $result = $handler->__invoke(new ListOrganizationNonConformitiesQuery(
      organizationId: self::ORG_ID,
    ));

    self::assertCount(1, $result->items);
    self::assertNull($result->items[0]->equipmentId);
    self::assertNull($result->items[0]->equipmentSerialNumber);
  }

  #[Test]
  public function testInvokeReturnsEmptyPaginatedResultWhenNoNonConformities(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())->method('findByOrganizationId')->willReturn([]);
    $nonConformityRepository->expects(self::once())->method('countByOrganizationId')->willReturn(0);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())->method('findEquipmentIdsByIds')->with([])->willReturn([]);

    /** @var EquipmentNamingPort&MockObject $equipmentNaming */
    $equipmentNaming = $this->createMock(EquipmentNamingPort::class);
    $equipmentNaming->expects(self::once())->method('findSerialNumbersByIds')->with([])->willReturn([]);

    $handler = new ListOrganizationNonConformitiesHandler(
      nonConformityRepository: $nonConformityRepository,
      inspectionRepository: $inspectionRepository,
      equipmentNaming: $equipmentNaming,
    );

    $result = $handler->__invoke(new ListOrganizationNonConformitiesQuery(
      organizationId: self::ORG_ID,
    ));

    self::assertEmpty($result->items);
    self::assertSame(0, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeThrowsWhenSeverityIsInvalid(): void
  {
    $handler = new ListOrganizationNonConformitiesHandler(
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListOrganizationNonConformitiesQuery(
      organizationId: self::ORG_ID,
      severity: 'not-a-severity',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenStatusIsInvalid(): void
  {
    $handler = new ListOrganizationNonConformitiesHandler(
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListOrganizationNonConformitiesQuery(
      organizationId: self::ORG_ID,
      status: 'not-a-status',
    ));
  }
}
