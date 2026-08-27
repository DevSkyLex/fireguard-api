<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{CanonicalInspectionRepositoryPort, InterventionScopePort};
use Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\{PatchCanonicalInspectionCommand, PatchCanonicalInspectionHandler};
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Domain\Exception\{CanonicalInspectionValidationException, InspectionNotFoundException, InspectionRevisionMismatchException};
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
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test PatchCanonicalInspectionHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PatchCanonicalInspectionHandler::class)]
final class PatchCanonicalInspectionHandlerTest extends TestCase
{
  // #region Constants
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440025';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
  // #endregion

  // #region Tests
  /**
   * Method testClosingAPublishedInspectionSavesTouchesAndAudits.
   *
   * @return void no return value
   */
  #[Test]
  public function testClosingAPublishedInspectionSavesTouchesAndAudits(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(interventionId: self::INTERVENTION_ID));
    $inspections->expects(self::once())->method('save');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionClosedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::INSPECTION_ID === $event->inspectionId
        && self::EQUIPMENT_ID === $event->equipmentId
        && 'pass' === $event->result,
    ));

    $result = $this->handler($inspections, $interventions, $dispatcher)(
      new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasStatus: true, status: 'closed'),
    );

    self::assertSame('closed', $result->status);
    self::assertSame('submitted', $result->previousStatus);
    self::assertSame(4, $result->revision);
  }

  /**
   * Method testSubmittingAPublishedInspectionAuditsTheSubmission.
   *
   * @return void no return value
   */
  #[Test]
  public function testSubmittingAPublishedInspectionAuditsTheSubmission(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(status: InspectionStatus::DRAFT));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(InspectionSubmittedEvent::class));

    $this->handler($inspections, eventDispatcher: $dispatcher)(
      new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasStatus: true, status: 'submitted'),
    );
  }

  /**
   * Method testCancellingThroughAPatchAuditsTheCancellation.
   *
   * @return void no return value
   */
  #[Test]
  public function testCancellingThroughAPatchAuditsTheCancellation(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionCancelledEvent
        && 'submitted' === $event->previousStatus,
    ));

    $this->handler($inspections, eventDispatcher: $dispatcher)(
      new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasStatus: true, status: 'cancelled'),
    );
  }

  /**
   * Method testAScratchpadPatchIsNeverAudited.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadPatchIsNeverAudited(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(
      recordStatus: InspectionRecordStatus::DRAFT,
      status: InspectionStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
    ));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($inspections, eventDispatcher: $dispatcher)(
      new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasStatus: true, status: 'closed'),
    );

    self::assertSame('closed', $result->status);
    self::assertNull($result->previousStatus);
  }

  /**
   * Method testAPatchWithoutAStatusChangeIsNeverAudited.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithoutAStatusChangeIsNeverAudited(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $this->handler($inspections, eventDispatcher: $dispatcher)(
      new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasNotes: true, notes: 'Updated'),
    );
  }

  /**
   * Method testARolledBackMutationAuditsNothing.
   *
   * The ledger is append-only and hash-chained on a separate database, so an
   * event dispatched inside the transaction would outlive a rollback with no
   * way to remove it.
   *
   * @return void no return value
   */
  #[Test]
  public function testARolledBackMutationAuditsNothing(): void
  {
    $inspections = $this->createStub(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static function (callable $operation): mixed {
        $operation();

        throw new RuntimeException('commit failed');
      },
    );

    $this->expectException(RuntimeException::class);

    $handler = new PatchCanonicalInspectionHandler(
      inspections: $inspections,
      interventions: $this->createStub(InterventionScopePort::class),
      eventDispatcher: $dispatcher,
      transactionManager: $transactionManager,
    );

    $handler(new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasStatus: true, status: 'closed'));
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

    $this->handler($inspections)(new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3));
  }

  /**
   * Method testAMalformedIdentifierIsNotFoundRatherThanInvalid.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierIsNotFoundRatherThanInvalid(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->expects(self::never())->method('findById');

    $this->expectException(InspectionNotFoundException::class);

    $this->handler($inspections)(new PatchCanonicalInspectionCommand('not-a-uuid', 3));
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeAnythingIsWritten.
   *
   * The processor already compared `If-Match` against a scope read on the
   * query bus — a different transaction. This is the check that closes the
   * window between the two.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeAnythingIsWritten(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection());
    $inspections->expects(self::never())->method('save');

    $this->expectException(InspectionRevisionMismatchException::class);

    $this->handler($inspections)(new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 1, hasStatus: true, status: 'closed'));
  }

  /**
   * Method testAnIllegalTransitionSavesNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnIllegalTransitionSavesNothing(): void
  {
    $inspections = $this->createMock(CanonicalInspectionRepositoryPort::class);
    $inspections->method('findById')->willReturn($this->inspection(status: InspectionStatus::DRAFT));
    $inspections->expects(self::never())->method('save');

    $this->expectException(CanonicalInspectionValidationException::class);

    $this->handler($inspections)(new PatchCanonicalInspectionCommand(self::INSPECTION_ID, 3, hasStatus: true, status: 'closed'));
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
   * @return PatchCanonicalInspectionHandler the handler under test
   */
  private function handler(
    ?CanonicalInspectionRepositoryPort $inspections = null,
    ?InterventionScopePort $interventions = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): PatchCanonicalInspectionHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new PatchCanonicalInspectionHandler(
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
