<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\{
  GetNonConformityHandler,
  GetNonConformityQuery,
  GetNonConformityResult
};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
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
  NonConformitySeverity,
  NonConformityStatus
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test GetNonConformityHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetNonConformityHandler::class)]
final class GetNonConformityHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORG_ID = '550e8400-e29b-41d4-a716-446655440009';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string OTHER_INSP_ID = '550e8400-e29b-41d4-a716-446655440008';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440005';
  // #endregion

  // #region Methods
  #[Test]
  public function testInvokeReturnsResultWithEveryField(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-10T08:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-12T09:30:00+00:00');
    $dueAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');
    $resolvedAt = new DateTimeImmutable('2026-01-20T14:15:00+00:00');

    $nonConformity = NonConformity::reconstitute(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: 'Extinguisher pressure below threshold',
      severity: NonConformitySeverity::HIGH,
      status: NonConformityStatus::IN_PROGRESS,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      dueAt: $dueAt,
      resolvedAt: $resolvedAt,
      notes: 'Awaiting replacement part.',
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeInspection(self::ORG_ID));

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn($nonConformity);

    $handler = new GetNonConformityHandler($inspectionRepository, $nonConformityRepository);

    $result = $handler->__invoke(new GetNonConformityQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));

    self::assertInstanceOf(GetNonConformityResult::class, $result);
    self::assertSame(self::NC_ID, $result->nonConformityId);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame('Extinguisher pressure below threshold', $result->description);
    self::assertSame('high', $result->severity);
    self::assertSame('in_progress', $result->status);
    self::assertSame($dueAt->format('c'), $result->dueAt);
    self::assertSame($resolvedAt->format('c'), $result->resolvedAt);
    self::assertSame('Awaiting replacement part.', $result->notes);
    self::assertSame($createdAt, $result->createdAt);
    self::assertSame($updatedAt, $result->updatedAt);
  }

  #[Test]
  public function testInvokeLeavesNullableTimestampsAndNotesUnset(): void
  {
    $nonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: 'Missing signage',
      severity: NonConformitySeverity::LOW,
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeInspection(self::ORG_ID));

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn($nonConformity);

    $handler = new GetNonConformityHandler($inspectionRepository, $nonConformityRepository);

    $result = $handler->__invoke(new GetNonConformityQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));

    self::assertSame('low', $result->severity);
    self::assertSame('open', $result->status);
    self::assertNull($result->dueAt);
    self::assertNull($result->resolvedAt);
    self::assertNull($result->notes);
  }

  #[Test]
  public function testInvokeThrowsWhenIdentifiersAreInvalid(): void
  {
    $handler = new GetNonConformityHandler(
      $this->createStub(InspectionRepositoryPort::class),
      $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetNonConformityQuery(
      organizationId: 'not-a-uuid',
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $handler = new GetNonConformityHandler(
      $inspectionRepository,
      $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new GetNonConformityQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionBelongsToAnotherOrganization(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeInspection(self::OTHER_ORG_ID));

    $handler = new GetNonConformityHandler(
      $inspectionRepository,
      $this->createStub(NonConformityRepositoryPort::class),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new GetNonConformityQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityNotFound(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeInspection(self::ORG_ID));

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn(null);

    $handler = new GetNonConformityHandler($inspectionRepository, $nonConformityRepository);

    $this->expectException(NonConformityNotFoundException::class);

    $handler->__invoke(new GetNonConformityQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityBelongsToAnotherInspection(): void
  {
    $nonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::OTHER_INSP_ID),
      description: 'Mismatched inspection',
      severity: NonConformitySeverity::MEDIUM,
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeInspection(self::ORG_ID));

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn($nonConformity);

    $handler = new GetNonConformityHandler($inspectionRepository, $nonConformityRepository);

    $this->expectException(NonConformityNotFoundException::class);

    $handler->__invoke(new GetNonConformityQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
    ));
  }

  // #region Helpers
  private function makeInspection(string $organizationId): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString($organizationId),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }
  // #endregion
}
