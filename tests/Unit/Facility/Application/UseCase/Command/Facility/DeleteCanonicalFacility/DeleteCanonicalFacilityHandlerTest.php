<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility;

use DateTimeImmutable;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\{CanonicalFacilityRepositoryPort, InterventionScopePort};
use Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility\{DeleteCanonicalFacilityCommand, DeleteCanonicalFacilityHandler};
use Facility\Domain\Event\Facility\FacilityArchivedEvent;
use Facility\Domain\Exception\{CanonicalFacilityConflictException, FacilityHasActiveDependentsException, FacilityNotFoundException, FacilityRevisionMismatchException};
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityRecordStatus, FacilityStatus, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test DeleteCanonicalFacilityHandlerTest.
 *
 * The canonical DELETE has three outcomes and they are not interchangeable —
 * hard delete, archive, idempotent no-op. Each is pinned here, along with the
 * two guards that stand in front of the hard delete and the ledger rows the
 * first and third must NOT produce.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCanonicalFacilityHandler::class)]
final class DeleteCanonicalFacilityHandlerTest extends TestCase
{
  // #region Constants
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440041';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440042';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440044';
  // #endregion

  // #region Tests
  /**
   * Method testAChildlessScratchpadIsHardDeletedAndNeverAudited.
   *
   * @return void no return value
   */
  #[Test]
  public function testAChildlessScratchpadIsHardDeletedAndNeverAudited(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(
      recordStatus: FacilityRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
    ));
    $facilities->method('countChildren')->willReturn(0);
    $facilities->expects(self::once())->method('delete');
    $facilities->expects(self::never())->method('save');
    $archivalGuard = $this->createMock(FacilityArchivalGuardPort::class);
    $archivalGuard->expects(self::once())->method('assertNoActiveDependents');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($facilities, $archivalGuard, $interventions, $dispatcher)(
      new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 3),
    );

    self::assertTrue($result->hardDeleted);
    self::assertFalse($result->archived);
  }

  /**
   * Method testAScratchpadWithChildrenIsRefusedBeforeTheDependencyGuard.
   *
   * The foreign key is `ON DELETE SET NULL`: removing the parent would
   * silently promote its whole sub-tree to root, with nothing in the response
   * to say so.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadWithChildrenIsRefusedBeforeTheDependencyGuard(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(recordStatus: FacilityRecordStatus::DRAFT));
    $facilities->method('countChildren')->willReturn(2);
    $facilities->expects(self::never())->method('delete');
    $archivalGuard = $this->createMock(FacilityArchivalGuardPort::class);
    $archivalGuard->expects(self::never())->method('assertNoActiveDependents');

    $this->expectException(CanonicalFacilityConflictException::class);
    $this->expectExceptionMessage('Cannot delete a facility that still has child facilities; move or remove them first.');

    $this->handler($facilities, $archivalGuard)(new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 3));
  }

  /**
   * Method testAScratchpadWithALiveDependentIsRefused.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadWithALiveDependentIsRefused(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(recordStatus: FacilityRecordStatus::DRAFT));
    $facilities->method('countChildren')->willReturn(0);
    $facilities->expects(self::never())->method('delete');
    $archivalGuard = $this->createStub(FacilityArchivalGuardPort::class);
    $archivalGuard->method('assertNoActiveDependents')
      ->willThrowException(FacilityHasActiveDependentsException::withActiveEquipment(self::FACILITY_ID));

    $this->expectException(FacilityHasActiveDependentsException::class);

    $this->handler($facilities, $archivalGuard)(new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 3));
  }

  /**
   * Method testAPublishedFacilityIsArchivedAndAudited.
   *
   * `archived` is the only REVERSIBLE retirement state of the three canonical
   * surfaces.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPublishedFacilityIsArchivedAndAudited(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $facilities->expects(self::once())->method('save');
    $facilities->expects(self::never())->method('delete');
    $archivalGuard = $this->createMock(FacilityArchivalGuardPort::class);
    $archivalGuard->expects(self::once())->method('assertNoActiveDependents');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityArchivedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::FACILITY_ID === $event->facilityId,
    ));

    $result = $this->handler($facilities, $archivalGuard, eventDispatcher: $dispatcher)(
      new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 3),
    );

    self::assertTrue($result->archived);
    self::assertFalse($result->hardDeleted);
  }

  /**
   * Method testARepeatDeleteIsAnIdempotentNoOpAndSkipsTheGuard.
   *
   * Nothing saved, nothing audited — and the guard is deliberately NOT run:
   * a no-op must stay a no-op, not start failing because a dependent appeared
   * after the facility was retired.
   *
   * @return void no return value
   */
  #[Test]
  public function testARepeatDeleteIsAnIdempotentNoOpAndSkipsTheGuard(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(status: FacilityStatus::ARCHIVED));
    $facilities->expects(self::never())->method('save');
    $facilities->expects(self::never())->method('delete');
    $archivalGuard = $this->createMock(FacilityArchivalGuardPort::class);
    $archivalGuard->expects(self::never())->method('assertNoActiveDependents');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($facilities, $archivalGuard, $interventions, $dispatcher)(
      new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 3),
    );

    self::assertFalse($result->archived);
    self::assertFalse($result->hardDeleted);
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeTheScratchpadBranch.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeTheScratchpadBranch(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(recordStatus: FacilityRecordStatus::DRAFT));
    $facilities->expects(self::never())->method('countChildren');
    $facilities->expects(self::never())->method('delete');

    $this->expectException(FacilityRevisionMismatchException::class);

    $this->handler($facilities)(new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 1));
  }

  /**
   * Method testAnUnknownFacilityIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownFacilityIsNotFound(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn(null);

    $this->expectException(FacilityNotFoundException::class);

    $this->handler($facilities)(new DeleteCanonicalFacilityCommand(self::FACILITY_ID, 3));
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?CanonicalFacilityRepositoryPort $facilities the canonical repository
   * @param ?FacilityArchivalGuardPort $archivalGuard the archival guard
   * @param ?InterventionScopePort $interventions the intervention scope port
   * @param ?EventDispatcherPort $eventDispatcher the event dispatcher
   *
   * @return DeleteCanonicalFacilityHandler the handler under test
   */
  private function handler(
    ?CanonicalFacilityRepositoryPort $facilities = null,
    ?FacilityArchivalGuardPort $archivalGuard = null,
    ?InterventionScopePort $interventions = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): DeleteCanonicalFacilityHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new DeleteCanonicalFacilityHandler(
      facilities: $facilities ?? $this->createStub(CanonicalFacilityRepositoryPort::class),
      archivalGuard: $archivalGuard ?? $this->createStub(FacilityArchivalGuardPort::class),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      transactionManager: $transactionManager,
    );
  }

  /**
   * Method facility.
   *
   * @param FacilityRecordStatus $recordStatus the record status
   * @param FacilityStatus $status the facility status
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalFacility a published, active site at revision 3
   */
  private function facility(
    FacilityRecordStatus $recordStatus = FacilityRecordStatus::PUBLISHED,
    FacilityStatus $status = FacilityStatus::ACTIVE,
    ?string $interventionId = null,
  ): CanonicalFacility {
    return CanonicalFacility::reconstitute(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      parentFacilityId: null,
      type: FacilityType::SITE,
      name: 'Main site',
      code: null,
      address: null,
      latitude: null,
      longitude: null,
      metadata: [],
      status: $status,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
