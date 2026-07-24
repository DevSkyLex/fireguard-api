<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\NonConformity\ListNonConformities;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesHandler, ListNonConformitiesQuery, NonConformityResult};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector,
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity
};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

#[CoversClass(ListNonConformitiesHandler::class)]
final class ListNonConformitiesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441001';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655441099';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655441002';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655441003';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655441004';

  #[Test]
  public function testInvokePassesFiltersPaginationAndSortingAndMapsResults(): void
  {
    $dueAt = new DateTimeImmutable('2026-08-01T12:00:00+00:00');

    $nonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Missing extinguisher',
      severity: NonConformitySeverity::HIGH,
      dueAt: $dueAt,
      notes: 'Check monthly',
    );

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findById')
      ->with(InspectionId::fromString(self::INSPECTION_ID))
      ->willReturn($this->inspection(self::ORG_ID));

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())
      ->method('findByInspectionId')
      ->with(
        NonConformityInspectionId::fromString(self::INSPECTION_ID),
        'high',
        'open',
        'fire',
        new Sorting('severity', SortDirection::ASC),
        15,
        30,
      )
      ->willReturn([$nonConformity]);
    $nonConformityRepository->expects(self::once())
      ->method('countByInspectionId')
      ->with(
        NonConformityInspectionId::fromString(self::INSPECTION_ID),
        'high',
        'open',
        'fire',
      )
      ->willReturn(7);

    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
    );

    $result = $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      severity: 'high',
      status: 'open',
      pagination: new Pagination(offset: 30, limit: 15),
      search: 'fire',
      sorting: new Sorting('severity', SortDirection::ASC),
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertInstanceOf(NonConformityResult::class, $result->items[0]);
    self::assertSame(self::NC_ID, $result->items[0]->nonConformityId);
    self::assertSame(self::INSPECTION_ID, $result->items[0]->inspectionId);
    self::assertSame('Missing extinguisher', $result->items[0]->description);
    self::assertSame('high', $result->items[0]->severity);
    self::assertSame('open', $result->items[0]->status);
    self::assertSame($dueAt->format('c'), $result->items[0]->dueAt);
    self::assertNull($result->items[0]->resolvedAt);
    self::assertSame('Check monthly', $result->items[0]->notes);
    self::assertInstanceOf(DateTimeImmutable::class, $result->items[0]->createdAt);
    self::assertInstanceOf(DateTimeImmutable::class, $result->items[0]->updatedAt);
    self::assertSame(7, $result->total);
    self::assertSame(15, $result->limit);
    self::assertSame(30, $result->offset);
  }

  #[Test]
  public function testInvokeReturnsEmptyPaginatedResultWhenNoNonConformities(): void
  {
    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->inspection(self::ORG_ID));

    /** @var NonConformityRepositoryPort&MockObject $nonConformityRepository */
    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::once())->method('findByInspectionId')->willReturn([]);
    $nonConformityRepository->expects(self::once())->method('countByInspectionId')->willReturn(0);

    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
    );

    $result = $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
    ));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertEmpty($result->items);
    self::assertSame(0, $result->total);
    self::assertSame(20, $result->limit);
    self::assertSame(0, $result->offset);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionDoesNotExist(): void
  {
    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findById')
      ->with(InspectionId::fromString(self::INSPECTION_ID))
      ->willReturn(null);

    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionBelongsToAnotherOrganization(): void
  {
    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->inspection(self::OTHER_ORG_ID));

    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSeverityIsInvalid(): void
  {
    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      severity: 'not-a-severity',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenStatusIsInvalid(): void
  {
    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      status: 'not-a-status',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenIdentifierIsNotAValidUuid(): void
  {
    $handler = new ListNonConformitiesHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListNonConformitiesQuery(
      organizationId: 'not-a-uuid',
      inspectionId: self::INSPECTION_ID,
    ));
  }

  private function inspection(string $organizationId): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString($organizationId),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      inspector: Inspector::forUser('550e8400-e29b-41d4-a716-446655441005', 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }
}
