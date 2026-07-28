<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityChildren;

use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\GetFacilityChildren\{GetFacilityChildrenHandler, GetFacilityChildrenQuery};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};

#[CoversClass(GetFacilityChildrenHandler::class)]
final class GetFacilityChildrenHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsPaginatedChildrenWithHasChildren(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442000');
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442001');
    $childId = new FacilityId('550e8400-e29b-41d4-a716-446655442002');

    $parent = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Site'),
    );

    $child = Facility::create(
      id: $childId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building'),
      parentFacilityId: $facilityId,
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($parent);
    $repository->expects(self::once())
      ->method('findChildren')
      ->with(
        $organizationId,
        $facilityId,
        false,
        null,
        self::anything(),
        5,
        10,
      )
      ->willReturn([$child]);
    $repository->expects(self::once())
      ->method('countChildrenByParentIds')
      ->willReturn([(string) $childId => 1]);
    $repository->expects(self::once())
      ->method('countChildren')
      ->willReturn(6);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new GetFacilityChildrenHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

    $result = $handler->__invoke(new GetFacilityChildrenQuery(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      pagination: new Pagination(offset: 10, limit: 5),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertSame(6, $result->total);
    self::assertSame(5, $result->limit);
    self::assertSame(10, $result->offset);
    self::assertCount(1, $result->items);
    self::assertSame((string) $childId, $result->items[0]->facilityId);
    self::assertTrue($result->items[0]->hasChildren);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityIsOutsideOrganization(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442010'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442011'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Site'),
    );

    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($facility);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);


    $handler = new GetFacilityChildrenHandler(facilityRepository: $repository, equipmentDependency: $equipmentDependency);

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new GetFacilityChildrenQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655442012',
      facilityId: '550e8400-e29b-41d4-a716-446655442010',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findById');

    $handler = new GetFacilityChildrenHandler(
      facilityRepository: $repository,
      equipmentDependency: $this->createStub(FacilityEquipmentDependencyPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetFacilityChildrenQuery(
      organizationId: 'not-a-uuid',
      facilityId: 'also-not-a-uuid',
    ));
  }
}
