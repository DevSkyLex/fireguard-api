<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\NonConformity\AddNonConformity;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Command\NonConformity\AddNonConformity\{
  AddNonConformityCommand,
  AddNonConformityHandler,
  AddNonConformityResult
};
use Inspection\Domain\Event\NonConformity\NonConformityRecordedEvent;
use Inspection\Domain\Exception\{
  InspectionAlreadyClosedException,
  InspectionNotFoundException
};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector,
  NonConformityId
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test AddNonConformityHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddNonConformityHandler::class)]
final class AddNonConformityHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440005';
  // #endregion

  // #region Methods
  #[Test]
  public function testInvokeAddsNonConformityAndReturnsResult(): void
  {
    $ncId = NonConformityId::fromString(self::NC_ID);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(NonConformityId::class)
      ->willReturn($ncId);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeDraftInspection());

    /** @var NonConformityRepositoryPort&MockObject $ncRepository */
    $ncRepository = $this->createMock(NonConformityRepositoryPort::class);
    $ncRepository->expects(self::once())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof NonConformityRecordedEvent
          && self::ORG_ID === $event->organizationId
          && self::INSP_ID === $event->inspectionId
          && self::NC_ID === $event->nonConformityId
          && 'high' === $event->severity,
      ));

    $handler = new AddNonConformityHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new AddNonConformityCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      description: 'Extinguisher pressure below threshold',
      severity: 'high',
    ));

    self::assertInstanceOf(AddNonConformityResult::class, $result);
    self::assertSame(self::NC_ID, $result->nonConformityId);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame('Extinguisher pressure below threshold', $result->description);
    self::assertSame('high', $result->severity);
    self::assertSame('open', $result->status);
    self::assertNull($result->dueAt);
    self::assertInstanceOf(DateTimeImmutable::class, $result->createdAt);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $ncRepository = $this->createStub(NonConformityRepositoryPort::class);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new AddNonConformityHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new AddNonConformityCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      description: 'Some issue',
      severity: 'low',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionIsClosed(): void
  {
    $uuidFactory = $this->createStub(UuidFactory::class);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->makeClosedInspection());

    /** @var NonConformityRepositoryPort&MockObject $ncRepository */
    $ncRepository = $this->createMock(NonConformityRepositoryPort::class);
    $ncRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new AddNonConformityHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $ncRepository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionAlreadyClosedException::class);

    $handler->__invoke(new AddNonConformityCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
      description: 'Some issue',
      severity: 'medium',
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

  private function makeClosedInspection(): Inspection
  {
    $inspection = $this->makeDraftInspection();
    $inspection->submit();
    $inspection->close();

    return $inspection;
  }
  // #endregion
}
