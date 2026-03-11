<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\ListFacilities;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Application\UseCase\Query\Facility\ListFacilities\{ListFacilitiesHandler, ListFacilitiesQuery};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;

use function array_map;

#[CoversClass(ListFacilitiesHandler::class)]
final class ListFacilitiesHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeFiltersArchivedFacilitiesWhenIncludeArchivedFalse(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441800');

    $activeFacility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441801'),
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Active Site'),
    );

    $archivedFacility = Facility::reconstitute(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441802'),
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Archived Building'),
      status: FacilityStatus::ARCHIVED,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$activeFacility, $archivedFacility]);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
      includeArchived: false,
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame('550e8400-e29b-41d4-a716-446655441801', $result->items[0]->facilityId);
    self::assertSame('active', $result->items[0]->status);
  }

  #[Test]
  public function testInvokeIncludesArchivedFacilitiesWhenIncludeArchivedTrue(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441810');

    $activeFacility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441811'),
      organizationId: $organizationId,
      type: FacilityType::ZONE,
      name: new FacilityName('Zone A'),
    );

    $archivedFacility = Facility::reconstitute(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441812'),
      organizationId: $organizationId,
      type: FacilityType::AREA,
      name: new FacilityName('Area B'),
      status: FacilityStatus::ARCHIVED,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([$activeFacility, $archivedFacility]);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
      includeArchived: true,
    ));

    self::assertCount(2, $result->items);

    $statuses = array_map(static fn (GetFacilityResult $r): string => $r->status, $result->items);
    self::assertContains('active', $statuses);
    self::assertContains('archived', $statuses);
  }

  #[Test]
  public function testInvokeReturnsEmptyListWhenNoFacilitiesExist(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([]);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441820',
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertEmpty($result->items);
  }

  #[Test]
  public function testInvokeDefaultsToExcludingArchived(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441830');

    $archivedFacility = Facility::reconstitute(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441831'),
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Old Site'),
      status: FacilityStatus::ARCHIVED,
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([$archivedFacility]);

    $handler = new ListFacilitiesHandler(facilityRepository: $repository);

    // Default query: includeArchived defaults to false
    $result = $handler->__invoke(new ListFacilitiesQuery(
      organizationId: (string) $organizationId,
    ));

    self::assertEmpty($result->items);
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
    $repository->method('findByOrganizationId')->willReturn([$facility]);

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
  }
}
