<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\Contract\Facility\FacilityListCriteria;
use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\ListFacilities\{ListFacilitiesHandler, ListFacilitiesQuery};
use Facility\Domain\Model\Facility\{Facility, FacilityDetails};
use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(ListFacilitiesHandler::class)]
final class ListFacilitiesHandlerTest extends TestCase
{
  /**
   * Method testDraftOnlyPathsExcludePublishedAndForeignDraftAncestors.
   *
   * Preserves the parent selector's visibility boundary when batched path projection is reorganized.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDraftOnlyPathsExcludePublishedAndForeignDraftAncestors(): void
  {
    $organization = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441800');
    $interventionId = '550e8400-e29b-41d4-a716-446655441804';
    $id = '550e8400-e29b-41d4-a716-446655441801';
    $publishedId = '550e8400-e29b-41d4-a716-446655441802';
    $draftId = '550e8400-e29b-41d4-a716-446655441803';
    $foreignId = '550e8400-e29b-41d4-a716-446655441805';
    $facility = Facility::create(new FacilityId($id), $organization, FacilityType::BUILDING, new FacilityName('Prepared building'));
    $draftAncestor = ['id' => $draftId, 'name' => 'Prepared site', 'type' => 'site'];
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([$facility]);
    $repository->expects(self::once())->method('findAncestorsByFacilityIds')->with($organization, [$id])->willReturn([$id => [
      ['id' => $publishedId, 'name' => 'Published site', 'type' => 'site'],
      $draftAncestor,
      ['id' => $foreignId, 'name' => 'Another draft', 'type' => 'site'],
    ]]);
    $repository->expects(self::once())->method('findProjectionContextsByFacilityIds')->with($organization, [$id, $publishedId, $draftId, $foreignId])->willReturn([
      $id => ['recordStatus' => 'draft', 'interventionId' => $interventionId, 'revision' => 4],
      $publishedId => ['recordStatus' => 'published', 'interventionId' => null, 'revision' => 1],
      $draftId => ['recordStatus' => 'draft', 'interventionId' => $interventionId, 'revision' => 2],
      $foreignId => ['recordStatus' => 'draft', 'interventionId' => $foreignId, 'revision' => 1],
    ]);
    $result = (new ListFacilitiesHandler($repository, $this->createStub(FacilityEquipmentDependencyPort::class), $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class)))(
      new ListFacilitiesQuery((string) $organization, includePath: true, parentForType: 'floor', interventionId: $interventionId, includePublishedParents: false),
    );
    self::assertSame([$draftAncestor], $result->items[0]->path);
    self::assertSame('draft', $result->items[0]->recordStatus);
    self::assertSame($interventionId, $result->items[0]->interventionId);
    self::assertSame(4, $result->items[0]->revision);
  }

  /**
   * Method testMoveOriginOutsidePublicationContextIsConcealedBeforeEligibilityReads.
   *
   * Refuses unreadable move origins before querying or counting parent candidates.
   *
   * @access public
   *
   * @param ?string $recordStatus stored publication state, or absent projection
   * @param ?string $contextInterventionId intervention retaining the draft
   * @param bool $includePublished whether the caller can see published parents
   *
   * @return void
   */
  #[Test]
  #[DataProvider('unreadableMovingContexts')]
  public function testMoveOriginOutsidePublicationContextIsConcealedBeforeEligibilityReads(?string $recordStatus, ?string $contextInterventionId, bool $includePublished): void
  {
    $organization = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441800');
    $id = new FacilityId('550e8400-e29b-41d4-a716-446655441801');
    $interventionId = '550e8400-e29b-41d4-a716-446655441804';
    $facility = Facility::create($id, $organization, FacilityType::BUILDING, new FacilityName('Move origin'));
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->with($id)->willReturn($facility);
    $contexts = null === $recordStatus ? [] : [(string) $id => ['recordStatus' => $recordStatus, 'interventionId' => $contextInterventionId, 'revision' => 3]];
    $repository->expects(self::once())->method('findProjectionContextsByFacilityIds')->with($organization, [(string) $id])->willReturn($contexts);
    $repository->expects(self::never())->method('findByOrganizationId');
    $repository->expects(self::never())->method('countByOrganizationId');
    $hierarchy = $this->createMock(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class);
    $hierarchy->expects(self::never())->method('eligibleParentIds');
    $handler = new ListFacilitiesHandler($repository, $this->createStub(FacilityEquipmentDependencyPort::class), $hierarchy);
    $this->expectException(\Facility\Domain\Exception\FacilityNotFoundException::class);
    $handler(new ListFacilitiesQuery((string) $organization, parentForFacilityId: (string) $id, interventionId: $interventionId, includePublishedParents: $includePublished));
  }

  /**
   * Method unreadableMovingContexts.
   *
   * Supplies absent, hidden published, foreign draft and removed origins.
   *
   * @access public
   *
   * @return iterable<string, array{?string, ?string, bool}> publication visibility denial cases
   */
  public static function unreadableMovingContexts(): iterable
  {
    yield 'missing projection' => [null, null, true];
    yield 'published parents unreadable' => ['published', null, false];
    yield 'another intervention draft' => ['draft', '550e8400-e29b-41d4-a716-446655441805', true];
    yield 'removed row' => ['removed', '550e8400-e29b-41d4-a716-446655441804', true];
  }

  #[Test]
  public function candidateEligibilityIsAppliedBeforeSearchCountAndPagination(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441800';
    $eligibleId = '550e8400-e29b-41d4-a716-446655441801';
    $hierarchy = $this->createMock(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('eligibleParentIds')->with($organizationId, 'floor', null)->willReturn([$eligibleId]);
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $criteria = new FacilityListCriteria(search: 'West', eligibleParentIds: [$eligibleId]);
    $repository->expects(self::once())->method('findByOrganizationId')->with(
      new FacilityOrganizationId($organizationId),
      false,
      $criteria,
      new Sorting('name', SortDirection::ASC),
      20,
      0,
    )->willReturn([]);
    $repository->expects(self::once())->method('countByOrganizationId')->with(new FacilityOrganizationId($organizationId), false, $criteria)->willReturn(205);
    $repository->expects(self::never())->method('findAncestorsByFacilityIds');
    $result = (new ListFacilitiesHandler($repository, $this->createStub(FacilityEquipmentDependencyPort::class), $hierarchy))(
      new ListFacilitiesQuery($organizationId, parentForType: 'floor', search: 'West'),
    );
    self::assertSame(205, $result->total);
  }

  #[Test]
  public function optInPathsAndHistoricalIssuesAreResolvedOnceForThePage(): void
  {
    $organization = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441800');
    $id = '550e8400-e29b-41d4-a716-446655441801';
    $facility = Facility::create(new FacilityId($id), $organization, FacilityType::BUILDING, new FacilityName('Historical root'));
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([$facility]);
    $repository->expects(self::once())->method('findAncestorsByFacilityIds')->with($organization, [$id])->willReturn([]);
    $repository->expects(self::once())->method('findProjectionContextsByFacilityIds')->with($organization, [$id])->willReturn([$id => ['recordStatus' => 'published', 'interventionId' => null, 'revision' => 12]]);
    $hierarchy = $this->createMock(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class);
    $hierarchy->expects(self::once())->method('issuesFor')->with((string) $organization, [$id])->willReturn([$id => ['missing_parent']]);
    $result = (new ListFacilitiesHandler($repository, $this->createStub(FacilityEquipmentDependencyPort::class), $hierarchy))(
      new ListFacilitiesQuery((string) $organization, includePath: true),
    );
    self::assertSame([], $result->items[0]->path);
    self::assertSame(['missing_parent'], $result->items[0]->hierarchyIssues);
    self::assertSame(12, $result->items[0]->revision);
  }

  #[Test]
  public function testInvokePassesFiltersPaginationAndSortingToRepository(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441800');

    $activeFacility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441801'),
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Active Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        $organizationId,
        false,
        new FacilityListCriteria(
          type: 'site',
          status: 'active',
          parentFacilityId: '550e8400-e29b-41d4-a716-446655441803',
          code: 'SITE-001',
          search: 'hq',
          hasCoordinates: true,
        ),
        new Sorting('createdAt', SortDirection::DESC),
        15,
        30,
      )
      ->willReturn([$activeFacility]);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        $organizationId,
        false,
        new FacilityListCriteria(
          type: 'site',
          status: 'active',
          parentFacilityId: '550e8400-e29b-41d4-a716-446655441803',
          code: 'SITE-001',
          search: 'hq',
          hasCoordinates: true,
        ),
      )
      ->willReturn(4);
    $repository->expects(self::once())
      ->method('countChildrenByParentIds')
      ->willReturn(['550e8400-e29b-41d4-a716-446655441801' => 2]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency, hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class));

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
      includeArchived: false,
      pagination: new \Shared\Application\Contract\Pagination\Pagination(offset: 30, limit: 15),
      type: 'site',
      status: 'active',
      parentFacilityId: '550e8400-e29b-41d4-a716-446655441803',
      code: 'SITE-001',
      hasCoordinates: true,
      search: 'hq',
      sorting: new Sorting('createdAt', SortDirection::DESC),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame('550e8400-e29b-41d4-a716-446655441801', $result->items[0]->facilityId);
    self::assertSame('active', $result->items[0]->status);
    self::assertTrue($result->items[0]->hasChildren);
    self::assertSame(4, $result->total);
    self::assertSame(15, $result->limit);
    self::assertSame(30, $result->offset);
  }

  #[Test]
  public function testInvokePassesHasCoordinatesFalseToRepository(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441804');

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        $organizationId,
        false,
        new FacilityListCriteria(hasCoordinates: false),
        new Sorting('name', SortDirection::ASC),
        20,
        0,
      )
      ->willReturn([]);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        $organizationId,
        false,
        new FacilityListCriteria(hasCoordinates: false),
      )
      ->willReturn(0);
    $repository->expects(self::once())
      ->method('countChildrenByParentIds')
      ->willReturn([]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);
    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency, hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class));

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
      hasCoordinates: false,
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertEmpty($result->items);
  }

  #[Test]
  public function testInvokeReturnsEmptyListWhenNoFacilitiesExist(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([]);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(0);
    $repository->expects(self::once())
      ->method('countChildrenByParentIds')
      ->willReturn([]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency, hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class));

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441820',
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertEmpty($result->items);
    self::assertSame(0, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeThrowsWhenTypeIsInvalid(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency, hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class));

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListFacilitiesQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441820',
      type: 'campus',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentFacilityIdIsInvalid(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency, hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class));

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListFacilitiesQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441830',
      parentFacilityId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenRootsOnlyIsCombinedWithParentFacilityId(): void
  {
    $handler = new ListFacilitiesHandler(
      facilityRepository: $this->createStub(FacilityRepositoryPort::class),
      equipmentDependency: $this->createStub(FacilityEquipmentDependencyPort::class),
      hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class),
    );

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('rootsOnly cannot be combined with parentFacilityId.');

    $handler->__invoke(new ListFacilitiesQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441831',
      parentFacilityId: '550e8400-e29b-41d4-a716-446655441832',
      rootsOnly: true,
    ));
  }

  #[Test]
  public function testInvokeMapsResultFieldsCorrectly(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441840');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655441843');

    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441841'),
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 3'),
      details: new FacilityDetails(
        parentFacilityId: $parentId,
        code: 'FLR-3',
        address: 'Wing B',
        metadata: ['capacity' => 50],
        coordinates: new FacilityCoordinates(48.8566, 2.3522),
      ),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->willReturn([$facility]);
    $repository->expects(self::once())->method('countByOrganizationId')->willReturn(1);
    $repository->expects(self::once())
      ->method('countChildrenByParentIds')
      ->willReturn(['550e8400-e29b-41d4-a716-446655441841' => 0]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency, hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class));

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
    ));

    self::assertCount(1, $result->items);

    $item = $result->items[0];
    self::assertSame('550e8400-e29b-41d4-a716-446655441841', $item->facilityId);
    self::assertSame((string) $organizationId, $item->organizationId);
    self::assertSame((string) $parentId, $item->parentFacilityId);
    self::assertSame('floor', $item->type);
    self::assertSame('Floor 3', $item->name);
    self::assertSame('FLR-3', $item->code);
    self::assertSame('active', $item->status);
    self::assertFalse($item->hasChildren);
    self::assertSame('Wing B', $item->address);
    self::assertSame(48.8566, $item->latitude);
    self::assertSame(2.3522, $item->longitude);
    self::assertSame(['capacity' => 50], $item->metadata);
    self::assertSame(1, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeCountsEquipmentInOneBatchedCallAndDefaultsMissingFacilitiesToZero(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441850');

    $stocked = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441851'),
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Stocked Site'),
    );
    $bare = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441852'),
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Bare Site'),
    );

    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([$stocked, $bare]);
    $repository->method('countByOrganizationId')->willReturn(2);
    $repository->method('countChildrenByParentIds')->willReturn([]);

    /** @var FacilityEquipmentDependencyPort&MockObject $equipmentDependency */
    $equipmentDependency = $this->createMock(FacilityEquipmentDependencyPort::class);
    // Once, not once per facility: a page of twenty rows must not become twenty
    // queries.
    $equipmentDependency->expects(self::once())
      ->method('countActiveEquipmentByFacility')
      ->with(
        (string) $organizationId,
        [
          '550e8400-e29b-41d4-a716-446655441851',
          '550e8400-e29b-41d4-a716-446655441852',
        ],
      )
      ->willReturn(['550e8400-e29b-41d4-a716-446655441851' => 7]);

    $handler = new ListFacilitiesHandler(
      facilityRepository: $repository,
      equipmentDependency: $equipmentDependency,
      hierarchy: $this->createStub(\Facility\Application\Port\Inbound\FacilityHierarchyPort::class),
    );

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
    ));

    self::assertSame(7, $result->items[0]->equipmentCount);
    // Absent from the port's answer means none, not unknown.
    self::assertSame(0, $result->items[1]->equipmentCount);
  }
}
