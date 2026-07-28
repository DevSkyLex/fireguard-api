<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Application\UseCase\Command\Inspection\CancelInspection\{
  CancelInspectionCommand,
  CancelInspectionHandler,
  CancelInspectionResult
};
use Inspection\Domain\Event\Inspection\InspectionCancelledEvent;
use Inspection\Domain\Exception\{
  InspectionAlreadyCancelledException,
  InspectionAlreadyClosedException,
  InspectionNotFoundException
};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector
};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(CancelInspectionHandler::class)]
final class CancelInspectionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testInvokeCancelsDraftInspectionLogically(): void
  {
    $inspection = $this->makeDraftInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    // Logical cancellation persists the row instead of deleting it.
    $repository->expects(self::never())->method('remove');
    $repository->expects(self::once())->method('save')->with($inspection);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof InspectionCancelledEvent
          && self::ORG_ID === $event->organizationId
          && self::INSP_ID === $event->inspectionId
          && self::EQUIP_ID === $event->equipmentId
          && 'draft' === $event->previousStatus,
      ));

    $handler = new CancelInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new CancelInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));

    self::assertInstanceOf(CancelInspectionResult::class, $result);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertTrue($inspection->status()->isCancelled());
  }

  #[Test]
  public function testInvokeCancelsSubmittedInspectionLogically(): void
  {
    $inspection = $this->makeSubmittedInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::never())->method('remove');
    $repository->expects(self::once())->method('save')->with($inspection);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    // The event captures the pre-mutation status of the aggregate.
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof InspectionCancelledEvent
          && 'submitted' === $event->previousStatus,
      ));

    $handler = new CancelInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new CancelInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));

    self::assertTrue($inspection->status()->isCancelled());
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CancelInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new CancelInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationMismatch(): void
  {
    $inspection = $this->makeDraftInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CancelInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new CancelInspectionCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionIsAlreadyClosed(): void
  {
    $inspection = $this->makeClosedInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('remove');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    // The aggregate rejects the transition, so nothing is persisted nor emitted.
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CancelInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionAlreadyClosedException::class);

    $handler->__invoke(new CancelInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionIsAlreadyCancelled(): void
  {
    $inspection = $this->makeCancelledInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('remove');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    // An already-cancelled inspection is rejected before any state change,
    // so no duplicate cancellation event may reach the ledger.
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CancelInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InspectionAlreadyCancelledException::class);

    $handler->__invoke(new CancelInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  // #region Helpers
  #[Test]
  public function testInvokeRejectsAMalformedIdentifier(): void
  {
    $handler = new CancelInspectionHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CancelInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: 'not-a-uuid',
    ));
  }

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

  private function makeSubmittedInspection(): Inspection
  {
    $inspection = $this->makeDraftInspection();
    $inspection->submit();

    return $inspection;
  }

  private function makeClosedInspection(): Inspection
  {
    $inspection = $this->makeSubmittedInspection();
    $inspection->close();

    return $inspection;
  }

  private function makeCancelledInspection(): Inspection
  {
    $inspection = $this->makeDraftInspection();
    $inspection->cancel();

    return $inspection;
  }
  // #endregion
}
