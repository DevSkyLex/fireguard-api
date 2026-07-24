<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\CloseInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionMaintenanceSynchronizerPort, InspectionRepositoryPort};
use Inspection\Application\UseCase\Command\Inspection\CloseInspection\{
  CloseInspectionCommand,
  CloseInspectionHandler,
  CloseInspectionResult
};
use Inspection\Domain\Event\Inspection\InspectionClosedEvent;
use Inspection\Domain\Exception\{
  InspectionNotFoundException,
  InspectionNotSubmittedException
};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test CloseInspectionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CloseInspectionHandler::class)]
final class CloseInspectionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';
  // #endregion

  // #region Methods
  #[Test]
  public function testInvokeClosesSubmittedInspectionAndReturnsResult(): void
  {
    $inspection = $this->makeSubmittedInspection();

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($inspection);
    $repository->expects(self::once())
      ->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof InspectionClosedEvent
          && self::ORG_ID === $event->organizationId
          && self::INSP_ID === $event->inspectionId
          && self::EQUIP_ID === $event->equipmentId
          && InspectionResult::PASS->value === $event->result,
      ));

    /** @var InspectionMaintenanceSynchronizerPort&MockObject $maintenanceSynchronizer */
    $maintenanceSynchronizer = $this->createMock(InspectionMaintenanceSynchronizerPort::class);
    $maintenanceSynchronizer->expects(self::once())
      ->method('onInspectionClosed')
      ->with(self::ORG_ID, self::EQUIP_ID, self::isInstanceOf(DateTimeImmutable::class));

    $handler = new CloseInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
      maintenanceSynchronizer: $maintenanceSynchronizer,
      logger: new NullLogger(),
    );

    $result = $handler->__invoke(new CloseInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));

    self::assertInstanceOf(CloseInspectionResult::class, $result);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame(InspectionStatus::CLOSED->value, $result->status);
    self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
  }

  #[Test]
  public function testInvokeSucceedsWhenMaintenanceSynchronizationFails(): void
  {
    $inspection = $this->makeSubmittedInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    $eventDispatcher = $this->createStub(EventDispatcherPort::class);

    /** @var InspectionMaintenanceSynchronizerPort&MockObject $maintenanceSynchronizer */
    $maintenanceSynchronizer = $this->createMock(InspectionMaintenanceSynchronizerPort::class);
    $maintenanceSynchronizer->expects(self::once())
      ->method('onInspectionClosed')
      ->willThrowException(new RuntimeException('boom'));

    $handler = new CloseInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
      maintenanceSynchronizer: $maintenanceSynchronizer,
      logger: new NullLogger(),
    );

    $result = $handler->__invoke(new CloseInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));

    self::assertSame(InspectionStatus::CLOSED->value, $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CloseInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
      maintenanceSynchronizer: $this->createStub(InspectionMaintenanceSynchronizerPort::class),
      logger: new NullLogger(),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new CloseInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationMismatch(): void
  {
    $inspection = $this->makeSubmittedInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CloseInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
      maintenanceSynchronizer: $this->createStub(InspectionMaintenanceSynchronizerPort::class),
      logger: new NullLogger(),
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new CloseInspectionCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      inspectionId: self::INSP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionIsDraft(): void
  {
    $inspection = $this->makeDraftInspection();

    $repository = $this->createStub(InspectionRepositoryPort::class);
    $repository->method('findPublishedById')->willReturn($inspection);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new CloseInspectionHandler(
      inspectionRepository: $repository,
      eventDispatcher: $eventDispatcher,
      maintenanceSynchronizer: $this->createStub(InspectionMaintenanceSynchronizerPort::class),
      logger: new NullLogger(),
    );

    $this->expectException(InspectionNotSubmittedException::class);

    $handler->__invoke(new CloseInspectionCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSP_ID,
    ));
  }

  // #region Helpers
  private function makeSubmittedInspection(): Inspection
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
    $inspection->submit();

    return $inspection;
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
  // #endregion
}
