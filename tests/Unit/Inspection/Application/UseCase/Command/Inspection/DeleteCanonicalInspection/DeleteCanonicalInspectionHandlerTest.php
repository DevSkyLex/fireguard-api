<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, InterventionScopePort};
use Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection\{DeleteCanonicalInspectionCommand, DeleteCanonicalInspectionHandler};
use Inspection\Domain\Event\Inspection\InspectionCancelledEvent;
use Inspection\Domain\Exception\{CanonicalInspectionConflictException, InspectionNotFoundException, InspectionRevisionMismatchException};
use Inspection\Domain\Model\Inspection\CanonicalInspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionRecordStatus,
  InspectionResult,
  InspectionStatus
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test DeleteCanonicalInspectionHandlerTest.
 *
 * The canonical DELETE has three outcomes and they are not interchangeable —
 * hard delete, logical cancel, idempotent no-op. Each is pinned here, along
 * with the ledger rows the first and third must NOT produce.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCanonicalInspectionHandler::class)]
final class DeleteCanonicalInspectionHandlerTest extends TestCase
{
  // #region Constants
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440025';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
  // #endregion

  // #region Tests
  /**
   * Method testAScratchpadIsHardDeletedAndNeverAudited.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadIsHardDeletedAndNeverAudited(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(
      recordStatus: InspectionRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
    ));
    $inspections->expects(self::once())->method('delete');
    $inspections->expects(self::never())->method('save');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($inspections, $interventions, $dispatcher)(
      new DeleteCanonicalInspectionCommand(self::INSPECTION_ID, 3),
    );

    self::assertTrue($result->hardDeleted);
    self::assertNull($result->previousStatus);
  }

  /**
   * Method testAPublishedInspectionIsCancelledAndAudited.
   *
   * Logical annulment, never a force-close: the row and its non-conformities
   * survive.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPublishedInspectionIsCancelledAndAudited(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection());
    $inspections->expects(self::once())->method('save');
    $inspections->expects(self::never())->method('delete');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionCancelledEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::INSPECTION_ID === $event->inspectionId
        && self::EQUIPMENT_ID === $event->equipmentId
        && 'submitted' === $event->previousStatus,
    ));

    $result = $this->handler($inspections, eventDispatcher: $dispatcher)(
      new DeleteCanonicalInspectionCommand(self::INSPECTION_ID, 3),
    );

    self::assertFalse($result->hardDeleted);
    self::assertSame('submitted', $result->previousStatus);
  }

  /**
   * Method testARepeatDeleteIsAnIdempotentNoOp.
   *
   * Nothing saved, nothing deleted, nothing audited — and the intervention is
   * still touched, matching what the processor did.
   *
   * @return void no return value
   */
  #[Test]
  public function testARepeatDeleteIsAnIdempotentNoOp(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(status: InspectionStatus::CANCELLED));
    $inspections->expects(self::never())->method('save');
    $inspections->expects(self::never())->method('delete');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($inspections, $interventions, $dispatcher)(
      new DeleteCanonicalInspectionCommand(self::INSPECTION_ID, 3),
    );

    self::assertFalse($result->hardDeleted);
    self::assertNull($result->previousStatus);
  }

  /**
   * Method testAClosedInspectionCannotBeCancelled.
   *
   * @return void no return value
   */
  #[Test]
  public function testAClosedInspectionCannotBeCancelled(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(status: InspectionStatus::CLOSED));
    $inspections->expects(self::never())->method('save');

    $this->expectException(CanonicalInspectionConflictException::class);
    $this->expectExceptionMessage('Closed inspections are immutable.');

    $this->handler($inspections)(new DeleteCanonicalInspectionCommand(self::INSPECTION_ID, 3));
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeTheScratchpadBranch.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeTheScratchpadBranch(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(recordStatus: InspectionRecordStatus::DRAFT));
    $inspections->expects(self::never())->method('delete');

    $this->expectException(InspectionRevisionMismatchException::class);

    $this->handler($inspections)(new DeleteCanonicalInspectionCommand(self::INSPECTION_ID, 1));
  }

  /**
   * Method testAnUnknownInspectionIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownInspectionIsNotFound(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn(null);

    $this->expectException(InspectionNotFoundException::class);

    $this->handler($inspections)(new DeleteCanonicalInspectionCommand(self::INSPECTION_ID, 3));
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?CanonicalInspectionRepositoryPort $inspections the canonical repository
   * @param ?InterventionScopePort $interventions the intervention scope port
   * @param ?EventDispatcherPort $eventDispatcher the event dispatcher
   *
   * @return DeleteCanonicalInspectionHandler the handler under test
   */
  private function handler(
    ?CanonicalInspectionRepositoryPort $inspections = null,
    ?InterventionScopePort $interventions = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): DeleteCanonicalInspectionHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new DeleteCanonicalInspectionHandler(
      inspections: $inspections ?? $this->createStub(CanonicalInspectionRepositoryPort::class),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      transactionManager: $transactionManager,
    );
  }

  /**
   * Method inspection.
   *
   * @param InspectionRecordStatus $recordStatus the record status
   * @param InspectionStatus $status the lifecycle status
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalInspection a published, submitted inspection at revision 3
   */
  private function inspection(
    InspectionRecordStatus $recordStatus = InspectionRecordStatus::PUBLISHED,
    InspectionStatus $status = InspectionStatus::SUBMITTED,
    ?string $interventionId = null,
  ): CanonicalInspection {
    return CanonicalInspection::reconstitute(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORGANIZATION_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIPMENT_ID),
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      status: $status,
      result: InspectionResult::PASS,
      notes: null,
      signature: null,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
