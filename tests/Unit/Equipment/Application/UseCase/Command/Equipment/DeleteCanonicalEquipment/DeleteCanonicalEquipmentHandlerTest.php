<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\Internal\CanonicalEquipmentRepositoryPort;
use Equipment\Application\Port\Outbound\InterventionScopePort;
use Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment\{DeleteCanonicalEquipmentCommand, DeleteCanonicalEquipmentHandler};
use Equipment\Domain\Event\Equipment\EquipmentDecommissionedEvent;
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentRevisionMismatchException};
use Equipment\Domain\Model\Equipment\{CanonicalEquipment, RestoredCanonicalEquipmentLifecycle, RestoredCanonicalEquipmentMetadata};
use Equipment\Domain\ValueObject\EquipmentCatalogDetails;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentRecordStatus, EquipmentStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test DeleteCanonicalEquipmentHandlerTest.
 *
 * The canonical DELETE has three outcomes and they are not interchangeable —
 * hard delete, decommission, idempotent no-op. Each is pinned here, along
 * with the maintenance-log syncs and ledger rows the first and third must
 * NOT produce.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCanonicalEquipmentHandler::class)]
final class DeleteCanonicalEquipmentHandlerTest extends TestCase
{
  // #region Constants
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440031';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440032';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440035';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440034';
  // #endregion

  // #region Tests
  /**
   * Method testAScratchpadIsHardDeletedSyncsNoLogAndIsNeverAudited.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadIsHardDeletedSyncsNoLogAndIsNeverAudited(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(
      recordStatus: EquipmentRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
    ));
    $equipment->expects(self::once())->method('delete');
    $equipment->expects(self::never())->method('save');
    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($equipment, $synchronizer, $interventions, $dispatcher)(
      new DeleteCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3),
    );

    self::assertTrue($result->hardDeleted);
    self::assertNull($result->previousStatus);
  }

  /**
   * Method testAPublishedAssetIsDecommissionedAndClosesItsMaintenanceLog.
   *
   * Retiring an asset that was under maintenance closes its still-open log,
   * mirroring `DecommissionEquipmentHandler`.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPublishedAssetIsDecommissionedAndClosesItsMaintenanceLog(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(
      status: EquipmentStatus::UNDER_MAINTENANCE,
      facilityId: self::FACILITY_ID,
    ));
    $equipment->expects(self::once())->method('save');
    $equipment->expects(self::never())->method('delete');
    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::once())->method('syncForStatusTransition')
      ->with(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'under_maintenance', 'decommissioned');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentDecommissionedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && 'under_maintenance' === $event->previousStatus,
    ));

    $result = $this->handler($equipment, $synchronizer, eventDispatcher: $dispatcher)(
      new DeleteCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3),
    );

    self::assertFalse($result->hardDeleted);
    self::assertSame('under_maintenance', $result->previousStatus);
  }

  /**
   * Method testARepeatDeleteIsAnIdempotentNoOp.
   *
   * Nothing saved, nothing deleted, no log sync, nothing audited — and the
   * intervention is still touched, matching what the processor did.
   *
   * @return void no return value
   */
  #[Test]
  public function testARepeatDeleteIsAnIdempotentNoOp(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(status: EquipmentStatus::DECOMMISSIONED));
    $equipment->expects(self::never())->method('save');
    $equipment->expects(self::never())->method('delete');
    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($equipment, $synchronizer, $interventions, $dispatcher)(
      new DeleteCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3),
    );

    self::assertFalse($result->hardDeleted);
    self::assertNull($result->previousStatus);
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeTheScratchpadBranch.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeTheScratchpadBranch(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(recordStatus: EquipmentRecordStatus::DRAFT));
    $equipment->expects(self::never())->method('delete');

    $this->expectException(EquipmentRevisionMismatchException::class);

    $this->handler($equipment)(new DeleteCanonicalEquipmentCommand(self::EQUIPMENT_ID, 1));
  }

  /**
   * Method testAnUnknownEquipmentIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownEquipmentIsNotFound(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn(null);

    $this->expectException(EquipmentNotFoundException::class);

    $this->handler($equipment)(new DeleteCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3));
  }
  // #endregion

  // #region Helpers
  #[Test]
  public function aParentMadeImmutableAfterPreflightIsRecheckedWithALockBeforeAnyWrite(): void
  {
    $insideTransaction = false;
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation) use (&$insideTransaction): mixed {
      $insideTransaction = true;

      try {
        return $operation();
      } finally {
        $insideTransaction = false;
      }
    });
    $parent = '550e8400-e29b-41d4-a716-446655440995';
    $actor = '550e8400-e29b-41d4-a716-446655440999';
    $interventions = $this->createMock(\Intervention\Application\Port\Inbound\InterventionResourcesPort::class);
    $interventions->expects(self::once())->method('mutationPermission')->with($parent, $actor, self::ORGANIZATION_ID, true)->willReturnCallback(static function () use (&$insideTransaction): never {
      self::assertTrue($insideTransaction);

      throw new \Intervention\Application\Contract\Resource\InterventionConflictException('Intervention became immutable after preflight.');
    });
    $authorization = $this->createStub(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class);
    $repository = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $repository->method('findById')->willReturn($this->equipment(recordStatus: EquipmentRecordStatus::DRAFT, interventionId: $parent));
    $repository->expects(self::never())->method('delete');
    $handler = $this->handler(equipment: $repository, transactionManager: $transactions, access: new \Equipment\Application\Service\EquipmentMediaAccess($authorization, $interventions));
    $this->expectException(\Intervention\Application\Contract\Resource\InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention became immutable after preflight.');
    $handler(new DeleteCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, userId: $actor));
  }

  /**
   * Method handler.
   *
   * @param ?CanonicalEquipmentRepositoryPort $equipment the canonical repository
   * @param ?EquipmentMaintenanceLogSynchronizerPort $synchronizer the maintenance-log synchronizer
   * @param ?InterventionScopePort $interventions the intervention scope port
   * @param ?EventDispatcherPort $eventDispatcher the event dispatcher
   *
   * @return DeleteCanonicalEquipmentHandler the handler under test
   */
  private function handler(
    ?CanonicalEquipmentRepositoryPort $equipment = null,
    ?EquipmentMaintenanceLogSynchronizerPort $synchronizer = null,
    ?InterventionScopePort $interventions = null,
    ?EventDispatcherPort $eventDispatcher = null,
    ?TransactionManagerPort $transactionManager = null,
    ?\Equipment\Application\Service\EquipmentMediaAccess $access = null,
  ): DeleteCanonicalEquipmentHandler {
    if (null === $transactionManager) {
      $transactionManager = $this->createStub(TransactionManagerPort::class);
      $transactionManager->method('transactional')->willReturnCallback(
        static fn (callable $operation): mixed => $operation(),
      );
    }

    return new DeleteCanonicalEquipmentHandler(
      equipment: $equipment ?? $this->createStub(CanonicalEquipmentRepositoryPort::class),
      maintenanceLogSynchronizer: $synchronizer ?? $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      transactionManager: $transactionManager,
      access: $access,
    );
  }

  /**
   * Method equipment.
   *
   * @param EquipmentRecordStatus $recordStatus the record status
   * @param EquipmentStatus $status the asset status
   * @param ?string $facilityId the assigned facility
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalEquipment a published, in-stock asset at revision 3
   */
  private function equipment(
    EquipmentRecordStatus $recordStatus = EquipmentRecordStatus::PUBLISHED,
    EquipmentStatus $status = EquipmentStatus::IN_STOCK,
    ?string $facilityId = null,
    ?string $interventionId = null,
  ): CanonicalEquipment {
    return CanonicalEquipment::reconstitute(
      id: EquipmentId::fromString(self::EQUIPMENT_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
      metadata: new RestoredCanonicalEquipmentMetadata(
        facilityId: $facilityId,
        type: 'fire_extinguisher',
        details: new EquipmentCatalogDetails(
          subType: null,
          brand: null,
          model: null,
          serialNumber: null,
          locationLabel: null,
        ),
      ),
      lifecycle: new RestoredCanonicalEquipmentLifecycle(
        recordStatus: $recordStatus,
        interventionId: $interventionId,
        status: $status,
        commissionedAt: null,
        revision: 3,
        updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
      ),
    );
  }
  // #endregion
}
