<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\ListFacilities\{ListFacilitiesHandler, ListFacilitiesQuery};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

#[CoversClass(ListFacilitiesHandler::class)]
final class ListFacilitiesHandlerTest extends TestCase
{
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
        'site',
        'active',
        '550e8400-e29b-41d4-a716-446655441803',
        'SITE-001',
        'hq',
        new Sorting('createdAt', SortDirection::DESC),
        15,
        30,
        false,
      )
      ->willReturn([$activeFacility]);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        $organizationId,
        false,
        'site',
        'active',
        '550e8400-e29b-41d4-a716-446655441803',
        'SITE-001',
        'hq',
        false,
      )
      ->willReturn(4);
    $repository->expects(self::once())
      ->method('countChildrenByParentIds')
      ->willReturn(['550e8400-e29b-41d4-a716-446655441801' => 2]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
      includeArchived: false,
      pagination: new \Shared\Application\Contract\Pagination\Pagination(offset: 30, limit: 15),
      type: 'site',
      status: 'active',
      parentFacilityId: '550e8400-e29b-41d4-a716-446655441803',
      code: 'SITE-001',
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


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

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


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

    $this->expectException(InvalidArgumentException::class);

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


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

    $this->expectException(InvalidArgumentException::class);

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
    );

    $this->expectException(InvalidArgumentException::class);
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
      parentFacilityId: $parentId,
      code: 'FLR-3',
      address: 'Wing B',
      metadata: ['capacity' => 50],
      coordinates: new FacilityCoordinates(48.8566, 2.3522),
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


    $handler = new ListFacilitiesHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

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
    );

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
    ));

    self::assertSame(7, $result->items[0]->equipmentCount);
    // Absent from the port's answer means none, not unknown.
    self::assertSame(0, $result->items[1]->equipmentCount);
  }
}
