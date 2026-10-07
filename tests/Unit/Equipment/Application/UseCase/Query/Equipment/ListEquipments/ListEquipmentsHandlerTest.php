<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use DateTimeImmutable;
use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceDueStatusPort, TagRepositoryPort};
use Equipment\Application\Port\Outbound\{FacilityNamingPort, FacilitySubtreeScopePort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\{ListEquipmentsHandler, ListEquipmentsQuery};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\EquipmentFacilityId;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use Maintenance\Application\Contract\Plan\MaintenanceEquipmentOperationsDue;
use Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(ListEquipmentsHandler::class)]
final class ListEquipmentsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655448001';

  private const string EQUIP_ID_1 = '550e8400-e29b-41d4-a716-446655448002';

  private const string EQUIP_ID_2 = '550e8400-e29b-41d4-a716-446655448003';

  #[Test]
  public function resolvesIndependentDeadlinesInOnePageBatch(): void
  {
    $repository = $this->createStub(EquipmentRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([
      Equipment::create(new EquipmentId(self::EQUIP_ID_1), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
      Equipment::create(new EquipmentId(self::EQUIP_ID_2), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
    ]);
    $repository->method('countByOrganizationId')->willReturn(2);
    $legacy = $this->createMock(MaintenanceDueStatusPort::class);
    $legacy->expects(self::never())->method('dueStatusesForEquipment');
    $operations = $this->createMock(MaintenanceOperationsDuePort::class);
    $operations->expects(self::once())->method('forEquipment')->with(self::ORG_ID, [self::EQUIP_ID_1, self::EQUIP_ID_2])->willReturn($this->independentDue());
    $handler = new ListEquipmentsHandler(
      $repository,
      $this->createStub(TagRepositoryPort::class),
      $legacy,
      $this->createStub(FacilityNamingPort::class),
      $this->createStub(FacilitySubtreeScopePort::class),
      operationsDue: $operations,
    );
    $result = $handler(new ListEquipmentsQuery(self::ORG_ID));
    self::assertSame(2, $result->total);
    self::assertSame('up_to_date', $result->items[0]->controlDueStatus);
    self::assertSame('overdue', $result->items[0]->serviceDueStatus);
    self::assertSame('2027-01-01T00:00:00+00:00', $result->items[0]->controlNextDueAt);
    self::assertSame('2026-09-01T00:00:00+00:00', $result->items[0]->serviceNextDueAt);
    self::assertSame('overdue', $result->items[1]->maintenanceDueStatus);
    self::assertSame('unscheduled', $result->items[1]->serviceDueStatus);
    self::assertNull($result->items[1]->serviceNextDueAt);
  }

  #[Test]
  public function theCompatibilityDueFilterMatchesControlsRatherThanServices(): void
  {
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->willReturn([
      Equipment::create(new EquipmentId(self::EQUIP_ID_1), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
      Equipment::create(new EquipmentId(self::EQUIP_ID_2), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
    ]);
    $repository->expects(self::never())->method('countByOrganizationId');
    $legacy = $this->createMock(MaintenanceDueStatusPort::class);
    $legacy->expects(self::never())->method('dueStatusesForEquipment');
    $operations = $this->createMock(MaintenanceOperationsDuePort::class);
    $operations->expects(self::once())->method('forEquipment')->with(self::ORG_ID, [self::EQUIP_ID_1, self::EQUIP_ID_2])->willReturn($this->independentDue());
    $handler = new ListEquipmentsHandler(
      $repository,
      $this->createStub(TagRepositoryPort::class),
      $legacy,
      $this->createStub(FacilityNamingPort::class),
      $this->createStub(FacilitySubtreeScopePort::class),
      operationsDue: $operations,
    );
    $result = $handler(new ListEquipmentsQuery(self::ORG_ID, maintenanceDueStatus: 'overdue'));
    self::assertSame(1, $result->total);
    self::assertSame(self::EQUIP_ID_2, $result->items[0]->equipmentId);
    self::assertSame('overdue', $result->items[0]->controlDueStatus);
    self::assertSame('unscheduled', $result->items[0]->serviceDueStatus);
  }

  #[Test]
  public function dueQueueCombinesSoonAndOverdueBeforeOnePagination(): void
  {
    $currentId = '550e8400-e29b-41d4-a716-446655448004';
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->willReturn([
      Equipment::create(new EquipmentId(self::EQUIP_ID_1), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
      Equipment::create(new EquipmentId(self::EQUIP_ID_2), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
      Equipment::create(new EquipmentId($currentId), new EquipmentOrganizationId(self::ORG_ID), EquipmentType::FIRE_EXTINGUISHER),
    ]);
    $repository->expects(self::never())->method('countByOrganizationId');
    $due = $this->createStub(MaintenanceDueStatusPort::class);
    $due->method('dueStatusesForEquipment')->willReturn([self::EQUIP_ID_1 => 'due_soon', self::EQUIP_ID_2 => 'overdue', $currentId => 'up_to_date']);
    $handler = new ListEquipmentsHandler($repository, $this->createStub(TagRepositoryPort::class), $due, $this->createStub(FacilityNamingPort::class), $this->createStub(FacilitySubtreeScopePort::class));
    $result = $handler(new ListEquipmentsQuery(self::ORG_ID, pagination: new Pagination(offset: 1, limit: 1), maintenanceDueStatus: 'due'));
    self::assertSame(2, $result->total);
    self::assertCount(1, $result->items);
    self::assertSame(self::EQUIP_ID_2, $result->items[0]->equipmentId);
    self::assertSame('overdue', $result->items[0]->maintenanceDueStatus);
  }

  #[Test]
  public function testDescendantCandidatesShareSearchAndPageCriteriaWithTheirCount(): void
  {
    $rootId = '550e8400-e29b-41d4-a716-446655448010';
    $roomId = '550e8400-e29b-41d4-a716-446655448011';
    $scope = $this->createMock(FacilitySubtreeScopePort::class);
    $scope->expects(self::once())->method('findPublishedSubtreeIds')->with(self::ORG_ID, $rootId)->willReturn([$rootId, $roomId]);
    $criteria = self::callback(static fn (EquipmentListCriteria $criteria): bool => null === $criteria->facilityId
      && [$rootId, $roomId] === $criteria->facilityIds && 'Hall' === $criteria->search);
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->with(
      self::equalTo(EquipmentOrganizationId::fromString(self::ORG_ID)),
      $criteria,
      self::anything(),
      100,
      200,
    )->willReturn([]);
    $repository->expects(self::once())->method('countByOrganizationId')->with(self::anything(), $criteria)->willReturn(205);

    $handler = new ListEquipmentsHandler(
      $repository,
      $this->createStub(TagRepositoryPort::class),
      $this->createStub(MaintenanceDueStatusPort::class),
      $this->createStub(FacilityNamingPort::class),
      $scope,
    );
    $result = $handler(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      facilityId: $rootId,
      search: 'Hall',
      pagination: new Pagination(offset: 200, limit: 100),
      includeDescendants: true,
    ));
    self::assertSame(205, $result->total);
    self::assertSame(200, $result->offset);
  }

  #[Test]
  public function testUnknownOrForeignDescendantScopeDoesNotQueryEquipment(): void
  {
    $scope = $this->createStub(FacilitySubtreeScopePort::class);
    $scope->method('findPublishedSubtreeIds')->willReturn([]);
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::never())->method('findByOrganizationId');
    $handler = new ListEquipmentsHandler(
      $repository,
      $this->createStub(TagRepositoryPort::class),
      $this->createStub(MaintenanceDueStatusPort::class),
      $this->createStub(FacilityNamingPort::class),
      $scope,
    );
    $this->expectException(EquipmentNotFoundException::class);
    $handler(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      facilityId: '550e8400-e29b-41d4-a716-446655448010',
      includeDescendants: true,
    ));
  }

  #[Test]
  public function testDescendantScopeRequiresFacilityFilter(): void
  {
    $scope = $this->createMock(FacilitySubtreeScopePort::class);
    $scope->expects(self::never())->method('findPublishedSubtreeIds');
    $handler = new ListEquipmentsHandler(
      $this->createStub(EquipmentRepositoryPort::class),
      $this->createStub(TagRepositoryPort::class),
      $this->createStub(MaintenanceDueStatusPort::class),
      $this->createStub(FacilityNamingPort::class),
      $scope,
    );
    $this->expectException(InvalidValueException::class);
    $handler(new ListEquipmentsQuery(organizationId: self::ORG_ID, includeDescendants: true));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidOrganizationId(): void
  {
    $handler = new ListEquipmentsHandler(
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: 'invalid-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidType(): void
  {
    $handler = new ListEquipmentsHandler(
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      type: 'not_a_valid_type',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidMaintenanceDueStatus(): void
  {
    $handler = new ListEquipmentsHandler(
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      tagRepository: $this->createStub(TagRepositoryPort::class),
      maintenanceDueStatusPort: $this->createStub(MaintenanceDueStatusPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      maintenanceDueStatus: 'not_a_real_status',
    ));
  }

  #[Test]
  public function testInvokeReturnsEmptyPaginatedResultWhenNoEquipments(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(0);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertSame(0, $result->total);
    self::assertCount(0, $result->items);
  }

  #[Test]
  public function testInvokeReturnsPaginatedResultWithItems(): void
  {
    $equipment1 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipment2 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_2),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SMOKE_DETECTOR,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$equipment1, $equipment2]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(2);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->with(self::ORG_ID, [self::EQUIP_ID_1, self::EQUIP_ID_2])
      ->willReturn([self::EQUIP_ID_1 => 'overdue']);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
    ));

    self::assertSame(2, $result->total);
    self::assertCount(2, $result->items);
    self::assertInstanceOf(GetEquipmentResult::class, $result->items[0]);
    self::assertSame(self::EQUIP_ID_1, $result->items[0]->equipmentId);
    self::assertSame('fire_extinguisher', $result->items[0]->type);
    self::assertSame('overdue', $result->items[0]->maintenanceDueStatus);
    self::assertSame(self::EQUIP_ID_2, $result->items[1]->equipmentId);
    self::assertSame('smoke_detector', $result->items[1]->type);
    // Not present in the batch's return map: must default to `unscheduled`, never null.
    self::assertSame('unscheduled', $result->items[1]->maintenanceDueStatus);
  }

  #[Test]
  public function testInvokePassesPaginationToRepository(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::anything(),
        self::equalTo(new EquipmentListCriteria()),
        self::equalTo(new Sorting('createdAt', SortDirection::ASC)),
        5,
        10,
      )
      ->willReturn([]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(self::anything(), self::equalTo(new EquipmentListCriteria()))
      ->willReturn(0);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 5, offset: 10),
    ));

    self::assertSame(5, $result->limit);
    self::assertSame(10, $result->offset);
  }

  #[Test]
  public function testInvokePassesSearchAndSortingToRepository(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::anything(),
        self::equalTo(new EquipmentListCriteria(search: 'sicli')),
        self::equalTo(new Sorting('brand', SortDirection::DESC)),
        10,
        0,
      )
      ->willReturn([]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        self::anything(),
        self::equalTo(new EquipmentListCriteria(search: 'sicli')),
      )
      ->willReturn(0);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
      search: 'sicli',
      sorting: new Sorting('brand', SortDirection::DESC),
    ));
  }

  #[Test]
  public function testInvokePassesBrandModelAndSubTypeFiltersToRepository(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::anything(),
        self::equalTo(new EquipmentListCriteria(brand: 'Sicli', model: 'ABC-9', subType: 'CO2')),
        self::equalTo(new Sorting('createdAt', SortDirection::ASC)),
        10,
        0,
      )
      ->willReturn([]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        self::anything(),
        self::equalTo(new EquipmentListCriteria(brand: 'Sicli', model: 'ABC-9', subType: 'CO2')),
      )
      ->willReturn(0);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([]);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
      brand: 'Sicli',
      model: 'ABC-9',
      subType: 'CO2',
    ));
  }

  #[Test]
  public function testInvokeFiltersByMaintenanceDueStatusAndPaginatesInMemory(): void
  {
    $equipment1 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipment2 = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_2),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SMOKE_DETECTOR,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    // The filtered path never calls countByOrganizationId (the total is
    // computed in-memory from the filtered candidate set instead).
    $equipmentRepository->expects(self::never())->method('countByOrganizationId');
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$equipment1, $equipment2]);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('findTagsByEquipmentIds')
      ->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->with(self::ORG_ID, [self::EQUIP_ID_1, self::EQUIP_ID_2])
      ->willReturn([self::EQUIP_ID_1 => 'overdue', self::EQUIP_ID_2 => 'up_to_date']);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
      maintenanceDueStatus: 'overdue',
    ));

    self::assertSame(1, $result->total);
    self::assertCount(1, $result->items);
    self::assertSame(self::EQUIP_ID_1, $result->items[0]->equipmentId);
    self::assertSame('overdue', $result->items[0]->maintenanceDueStatus);
  }

  #[Test]
  public function testInvokeResolvesFacilityNamesForTheDueStatusFilteredPage(): void
  {
    $facilityId = '550e8400-e29b-41d4-a716-446655448010';

    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString($facilityId),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$equipment]);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())->method('findTagsByEquipmentIds')->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([self::EQUIP_ID_1 => 'overdue']);

    // The de-duplicated facility id set of the in-memory page is what gets
    // resolved — one naming lookup for the whole page, never one per row.
    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([$facilityId])
      ->willReturn([$facilityId => 'Building A']);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $facilityNaming,
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
      maintenanceDueStatus: 'overdue',
    ));

    self::assertCount(1, $result->items);
    self::assertSame($facilityId, $result->items[0]->facilityId);
    self::assertSame('Building A', $result->items[0]->facilityName);
  }

  #[Test]
  public function testInvokeResolvesFacilityNamesOnTheMainListPath(): void
  {
    $facilityId = '550e8400-e29b-41d4-a716-446655448011';

    $assigned = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $assigned->assignToFacility(
      EquipmentFacilityId::fromString($facilityId),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $unassigned = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_2),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::SMOKE_DETECTOR,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$assigned, $unassigned]);
    $equipmentRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(2);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())->method('findTagsByEquipmentIds')->willReturn([]);

    /** @var MaintenanceDueStatusPort&MockObject $maintenanceDueStatusPort */
    $maintenanceDueStatusPort = $this->createMock(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->expects(self::once())
      ->method('dueStatusesForEquipment')
      ->willReturn([]);

    // ONE batch lookup for the whole page — the regression this guards was the
    // main path calling toResult() without the facilityName argument at all.
    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with([$facilityId])
      ->willReturn([$facilityId => 'Building A']);

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $facilityNaming,
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
    ));

    self::assertCount(2, $result->items);
    self::assertSame('Building A', $result->items[0]->facilityName);
    self::assertNull($result->items[1]->facilityName);
  }

  #[Test]
  public function testInvokeSkipsTheNamingLookupWhenNoEquipmentIsAssigned(): void
  {
    $unassigned = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID_1),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findByOrganizationId')->willReturn([$unassigned]);
    $equipmentRepository->method('countByOrganizationId')->willReturn(1);

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findTagsByEquipmentIds')->willReturn([]);

    $maintenanceDueStatusPort = $this->createStub(MaintenanceDueStatusPort::class);
    $maintenanceDueStatusPort->method('dueStatusesForEquipment')->willReturn([]);

    /** @var FacilityNamingPort&MockObject $facilityNaming */
    $facilityNaming = $this->createMock(FacilityNamingPort::class);
    $facilityNaming->expects(self::never())->method('findNamesByIds');

    $handler = new ListEquipmentsHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceDueStatusPort: $maintenanceDueStatusPort,
      facilityNaming: $facilityNaming,
      facilitySubtree: $this->createStub(FacilitySubtreeScopePort::class),
    );

    $result = $handler->__invoke(new ListEquipmentsQuery(
      organizationId: self::ORG_ID,
      pagination: new Pagination(limit: 10, offset: 0),
    ));

    self::assertCount(1, $result->items);
    self::assertNull($result->items[0]->facilityName);
  }

  /**
   * @return array<string, MaintenanceEquipmentOperationsDue> deadlines that deliberately disagree by operation kind
   */
  private function independentDue(): array
  {
    return [
      self::EQUIP_ID_1 => new MaintenanceEquipmentOperationsDue(
        'up_to_date',
        'overdue',
        new DateTimeImmutable('2027-01-01T00:00:00+00:00'),
        new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        'plans',
      ),
      self::EQUIP_ID_2 => new MaintenanceEquipmentOperationsDue(
        'overdue',
        'unscheduled',
        new DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        null,
        'plans',
      ),
    ];
  }
}
