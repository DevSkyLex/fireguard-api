<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\ListFacilities;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\ListFacilities\{ListFacilitiesHandler, ListFacilitiesQuery};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
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
      )
      ->willReturn(4);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

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

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

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

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

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

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListFacilitiesQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441830',
      parentFacilityId: 'not-a-uuid',
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
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findByOrganizationId')->willReturn([$facility]);
    $repository->expects(self::once())->method('countByOrganizationId')->willReturn(1);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

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
    self::assertSame('Wing B', $item->address);
    self::assertSame(['capacity' => 50], $item->metadata);
    self::assertSame(1, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }
}
