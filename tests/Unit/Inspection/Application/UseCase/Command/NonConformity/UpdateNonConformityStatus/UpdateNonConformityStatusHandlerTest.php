<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\{
  UpdateNonConformityStatusCommand,
  UpdateNonConformityStatusHandler,
  UpdateNonConformityStatusResult
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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateNonConformityStatusHandler::class)]
final class UpdateNonConformityStatusHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440004';

  #[Test]
  public function testInvokeUpdatesStatusAndSavesNonConformity(): void
  {
    $inspection = $this->makeDraftInspection();
    $nonConformity = $this->makeOpenNonConformity();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    /** @var NonConformityRepositoryPort&MockObject $ncRepository */
    $ncRepository = $this->createMock(NonConformityRepositoryPort::class);
    $ncRepository->method('findById')->willReturn($nonConformity);
    $ncRepository->expects(self::once())->method('save');

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $result = $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'in_progress',
    ));

    self::assertInstanceOf(UpdateNonConformityStatusResult::class, $result);
    self::assertSame(self::NC_ID, $result->nonConformityId);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame('in_progress', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $ncRepository = $this->createStub(NonConformityRepositoryPort::class);

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'in_progress',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationMismatch(): void
  {
    $inspection = $this->makeDraftInspection();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $ncRepository = $this->createStub(NonConformityRepositoryPort::class);

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'in_progress',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityNotFound(): void
  {
    $inspection = $this->makeDraftInspection();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $ncRepository = $this->createStub(NonConformityRepositoryPort::class);
    $ncRepository->method('findById')->willReturn(null);

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $this->expectException(NonConformityNotFoundException::class);

    $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'in_progress',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityBelongsToDifferentInspection(): void
  {
    $inspection = $this->makeDraftInspection();

    $otherInspectionNC = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString('550e8400-e29b-41d4-a716-999999999999'),
      description: 'Other inspection NC',
      severity: NonConformitySeverity::LOW,
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $ncRepository = $this->createStub(NonConformityRepositoryPort::class);
    $ncRepository->method('findById')->willReturn($otherInspectionNC);

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $this->expectException(NonConformityNotFoundException::class);

    $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'in_progress',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityAlreadyResolved(): void
  {
    $inspection = $this->makeDraftInspection();

    $resolvedNC = NonConformity::reconstitute(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: 'Already resolved issue',
      severity: NonConformitySeverity::HIGH,
      status: NonConformityStatus::DONE,
      createdAt: new DateTimeImmutable('2026-01-10T08:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-12T10:00:00+00:00'),
      resolvedAt: new DateTimeImmutable('2026-01-12T10:00:00+00:00'),
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    /** @var NonConformityRepositoryPort&MockObject $ncRepository */
    $ncRepository = $this->createMock(NonConformityRepositoryPort::class);
    $ncRepository->method('findById')->willReturn($resolvedNC);
    $ncRepository->expects(self::never())->method('save');

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $this->expectException(\Inspection\Domain\Exception\NonConformityAlreadyResolvedException::class);

    $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'in_progress',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenStatusIsInvalid(): void
  {
    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::never())->method('findById');

    /** @var NonConformityRepositoryPort&MockObject $ncRepository */
    $ncRepository = $this->createMock(NonConformityRepositoryPort::class);
    $ncRepository->expects(self::never())->method('save');

    $handler = new UpdateNonConformityStatusHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new UpdateNonConformityStatusCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      nonConformityId: self::NC_ID,
      status: 'not_a_valid_status',
    ));
  }

  // #region Helpers
  private function makeDraftInspection(): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }

  private function makeOpenNonConformity(): NonConformity
  {
    return NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: 'Extinguisher pressure below threshold',
      severity: NonConformitySeverity::HIGH,
    );
  }
  // #endregion
}
