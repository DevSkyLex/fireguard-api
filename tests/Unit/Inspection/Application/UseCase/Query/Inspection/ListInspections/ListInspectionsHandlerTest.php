<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Inspection\ListInspections;

use DateTimeImmutable;
use Inspection\Application\Contract\Inspection\{InspectionExecutionCriteria, InspectionInspectorCriteria, InspectionListCriteria, InspectionSubjectCriteria};
use Inspection\Application\Port\Outbound\{EquipmentNamingPort, FacilityNamingPort};
use Inspection\Application\Port\Outbound\Internal\{ChecklistRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
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
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\Exception\InvalidValueException;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\UserDirectoryPort;

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
        new InspectionListCriteria(
          subject: new InspectionSubjectCriteria(self::EQUIPMENT_ID, '550e8400-e29b-41d4-a716-446655449005', '550e8400-e29b-41d4-a716-446655449006'),
          execution: new InspectionExecutionCriteria('pass', 'draft', '2026-01-01T00:00:00+00:00', '2026-01-31T23:59:59+00:00'),
          inspector: new InspectionInspectorCriteria(userId: '550e8400-e29b-41d4-a716-446655449004'),
          search: 'john',
        ),
        new Sorting('performedAt', SortDirection::DESC),
        15,
        30,
      )
      ->willReturn([$inspection]);
    $inspectionRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        InspectionOrganizationId::fromString(self::ORG_ID),
        new InspectionListCriteria(
          subject: new InspectionSubjectCriteria(self::EQUIPMENT_ID, '550e8400-e29b-41d4-a716-446655449005', '550e8400-e29b-41d4-a716-446655449006'),
          execution: new InspectionExecutionCriteria('pass', 'draft', '2026-01-01T00:00:00+00:00', '2026-01-31T23:59:59+00:00'),
          inspector: new InspectionInspectorCriteria(userId: '550e8400-e29b-41d4-a716-446655449004'),
          search: 'john',
        ),
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

    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::never())->method('findByIds');
    $users->expects(self::never())->method('findById');

    $handler = new ListInspectionsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
      users: $users,
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
  public function testInspectorProfilesAreResolvedOnceForTheWholePage(): void
  {
    $knownUser = '550e8400-e29b-41d4-a716-446655449004';
    $unknownUser = '550e8400-e29b-41d4-a716-446655449007';
    $inspections = [
      $this->inspection(self::INSPECTION_ID, Inspector::forUser($knownUser, 'Stored inspector')),
      $this->inspection('550e8400-e29b-41d4-a716-446655449008', Inspector::forUser($knownUser, 'Same inspector')),
      $this->inspection('550e8400-e29b-41d4-a716-446655449009', Inspector::forUser($unknownUser, 'Retained inspector')),
      $this->inspection('550e8400-e29b-41d4-a716-446655449010', Inspector::forExternal('External auditor', 'Audit Ltd')),
    ];
    $profile = $this->profile($knownUser);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::once())->method('findByIds')->with([$knownUser, $unknownUser])->willReturn([$knownUser => $profile]);
    $users->expects(self::never())->method('findById');
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findByOrganizationId')->willReturn($inspections);
    $inspectionRepository->method('countByOrganizationId')->willReturn(4);
    $nonConformities = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformities->expects(self::once())->method('countsByInspectionIds')
      ->with([self::INSPECTION_ID, '550e8400-e29b-41d4-a716-446655449008', '550e8400-e29b-41d4-a716-446655449009', '550e8400-e29b-41d4-a716-446655449010'])
      ->willReturn([self::INSPECTION_ID => 2]);

    $handler = new ListInspectionsHandler($inspectionRepository, $nonConformities, $this->createStub(EquipmentNamingPort::class), $this->createStub(FacilityNamingPort::class), $this->createStub(ChecklistRepositoryPort::class), users: $users);
    $result = $handler->__invoke(new ListInspectionsQuery(self::ORG_ID));

    self::assertCount(4, $result->items);
    self::assertSame(4, $result->total);
    self::assertSame($profile, $result->items[0]->inspectorProfile);
    self::assertSame($profile, $result->items[1]->inspectorProfile);
    self::assertSame(2, $result->items[0]->nonConformitiesCount);
    self::assertNull($result->items[2]->inspectorProfile);
    self::assertSame('Retained inspector', $result->items[2]->inspectorName);
    self::assertNull($result->items[3]->inspectorProfile);
    self::assertSame('External auditor', $result->items[3]->inspectorName);
  }

  #[Test]
  public function testAnUnavailableAccountDirectoryPreservesTheAuthorizedInspectionPage(): void
  {
    $userId = '550e8400-e29b-41d4-a716-446655449004';
    $inspection = $this->inspection(self::INSPECTION_ID, Inspector::forUser($userId, 'Retained inspector'));
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findByOrganizationId')->willReturn([$inspection]);
    $inspectionRepository->method('countByOrganizationId')->willReturn(1);
    $users = $this->createMock(UserDirectoryPort::class);
    $users->expects(self::once())->method('findByIds')->with([$userId])->willThrowException(new RuntimeException('Account directory unavailable.'));

    $handler = new ListInspectionsHandler($inspectionRepository, $this->createStub(NonConformityRepositoryPort::class), $this->createStub(EquipmentNamingPort::class), $this->createStub(FacilityNamingPort::class), $this->createStub(ChecklistRepositoryPort::class), users: $users);
    $result = $handler->__invoke(new ListInspectionsQuery(self::ORG_ID));

    self::assertCount(1, $result->items);
    self::assertSame(self::INSPECTION_ID, $result->items[0]->inspectionId);
    self::assertSame($userId, $result->items[0]->inspectorUserId);
    self::assertSame('Retained inspector', $result->items[0]->inspectorName);
    self::assertNull($result->items[0]->inspectorProfile);
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

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListInspectionsQuery(
      organizationId: self::ORG_ID,
      performedAtFrom: '2026-02-01T00:00:00+00:00',
      performedAtTo: '2026-01-01T00:00:00+00:00',
    ));
  }

  #[Test]
  public function testInvokeRejectsAMalformedEquipmentFilter(): void
  {
    $handler = new ListInspectionsHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      equipmentNaming: $this->createStub(EquipmentNamingPort::class),
      facilityNaming: $this->createStub(FacilityNamingPort::class),
      checklistRepository: $this->createStub(ChecklistRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListInspectionsQuery(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
    ));
  }

  private function inspection(string $id, Inspector $inspector): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString($id),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      inspector: $inspector,
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }

  private function profile(string $id): UserView
  {
    return new UserView($id, 'inspector', 'inspector@example.com', 'Ada', 'Example', 'avatar.png', 'active', true, null, new DateTimeImmutable('2026-01-15T10:00:00+00:00'), null, true);
  }
}
