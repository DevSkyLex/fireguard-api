<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\{CanonicalEquipmentRepositoryPort, FacilityValidationPort, InterventionScopePort};
use Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\{PatchCanonicalEquipmentCommand, PatchCanonicalEquipmentHandler};
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Equipment\Domain\Exception\{CanonicalEquipmentValidationException, EquipmentNotFoundException, EquipmentRevisionMismatchException};
use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentRecordStatus, EquipmentStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test PatchCanonicalEquipmentHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PatchCanonicalEquipmentHandler::class)]
final class PatchCanonicalEquipmentHandlerTest extends TestCase
{
  // #region Constants
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440031';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440032';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440035';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440034';
  // #endregion

  // #region Tests
  /**
   * Method testCommissioningSavesSyncsTheLogTouchesAndAudits.
   *
   * @return void no return value
   */
  #[Test]
  public function testCommissioningSavesSyncsTheLogTouchesAndAudits(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(facilityId: self::FACILITY_ID, interventionId: self::INTERVENTION_ID));
    $equipment->expects(self::once())->method('save');
    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::once())->method('syncForStatusTransition')
      ->with(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'in_stock', 'operational');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof EquipmentCommissionedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::EQUIPMENT_ID === $event->equipmentId
        && self::FACILITY_ID === $event->facilityId
        && 'in_stock' === $event->previousStatus,
    ));

    $result = $this->handler($equipment, synchronizer: $synchronizer, interventions: $interventions, eventDispatcher: $dispatcher)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasStatus: true, status: 'operational'),
    );

    self::assertSame('operational', $result->status);
    self::assertSame('in_stock', $result->previousStatus);
    self::assertSame(4, $result->revision);
  }

  /**
   * Method testEnteringMaintenanceAuditsTheRightEvent.
   *
   * @return void no return value
   */
  #[Test]
  public function testEnteringMaintenanceAuditsTheRightEvent(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(
      status: EquipmentStatus::OPERATIONAL,
      facilityId: self::FACILITY_ID,
    ));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')
      ->with(self::isInstanceOf(EquipmentPutUnderMaintenanceEvent::class));

    $this->handler($equipment, eventDispatcher: $dispatcher)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasStatus: true, status: 'under_maintenance'),
    );
  }

  /**
   * Method testReturningToStockAuditsTheRightEvent.
   *
   * @return void no return value
   */
  #[Test]
  public function testReturningToStockAuditsTheRightEvent(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(
      status: EquipmentStatus::OPERATIONAL,
      facilityId: self::FACILITY_ID,
    ));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')
      ->with(self::isInstanceOf(EquipmentReturnedToStockEvent::class));

    $this->handler($equipment, eventDispatcher: $dispatcher)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasStatus: true, status: 'in_stock'),
    );
  }

  /**
   * Method testDecommissioningThroughAPatchAuditsTheRightEvent.
   *
   * @return void no return value
   */
  #[Test]
  public function testDecommissioningThroughAPatchAuditsTheRightEvent(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(facilityId: self::FACILITY_ID));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')
      ->with(self::isInstanceOf(EquipmentDecommissionedEvent::class));

    $this->handler($equipment, eventDispatcher: $dispatcher)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasStatus: true, status: 'decommissioned'),
    );
  }

  /**
   * Method testAScratchpadPatchSyncsNoLogAndAuditsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadPatchSyncsNoLogAndAuditsNothing(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(
      recordStatus: EquipmentRecordStatus::DRAFT,
      facilityId: self::FACILITY_ID,
      interventionId: self::INTERVENTION_ID,
    ));
    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($equipment, synchronizer: $synchronizer, eventDispatcher: $dispatcher)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasStatus: true, status: 'operational'),
    );

    self::assertNull($result->previousStatus);
  }

  /**
   * Method testAPatchWithoutAStatusChangeSyncsNoLogAndAuditsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithoutAStatusChangeSyncsNoLogAndAuditsNothing(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment());
    $synchronizer = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $synchronizer->expects(self::never())->method('syncForStatusTransition');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $this->handler($equipment, synchronizer: $synchronizer, eventDispatcher: $dispatcher)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasBrand: true, brand: 'Kidde'),
    );
  }

  /**
   * Method testAFacilityFromAnotherOrganizationIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAFacilityFromAnotherOrganizationIsRejected(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment());
    $equipment->expects(self::never())->method('save');
    $facilityValidation = $this->createStub(FacilityValidationPort::class);
    $facilityValidation->method('belongsToOrganization')->willReturn(false);

    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('Facility must belong to the same organization.');

    $this->handler($equipment, facilityValidation: $facilityValidation)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasFacility: true, facilityId: self::FACILITY_ID),
    );
  }

  /**
   * Method testANullNonNullableFieldIsRejectedBeforeTheFacilityIsChecked.
   *
   * The order is the processor's, and therefore what a client sending both
   * mistakes at once observes.
   *
   * @return void no return value
   */
  #[Test]
  public function testANullNonNullableFieldIsRejectedBeforeTheFacilityIsChecked(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment());
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::never())->method('belongsToOrganization');

    $this->expectException(CanonicalEquipmentValidationException::class);
    $this->expectExceptionMessage('Equipment status cannot be null.');

    $this->handler($equipment, facilityValidation: $facilityValidation)(
      new PatchCanonicalEquipmentCommand(
        self::EQUIPMENT_ID,
        3,
        hasStatus: true,
        status: null,
        hasFacility: true,
        facilityId: self::FACILITY_ID,
      ),
    );
  }

  /**
   * Method testDetachingAFacilityNeverAsksTheFacilityModule.
   *
   * `"facility": null` detaches; there is nothing to validate.
   *
   * @return void no return value
   */
  #[Test]
  public function testDetachingAFacilityNeverAsksTheFacilityModule(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(facilityId: self::FACILITY_ID));
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::never())->method('belongsToOrganization');

    $result = $this->handler($equipment, facilityValidation: $facilityValidation)(
      new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasFacility: true, facilityId: null),
    );

    self::assertSame(4, $result->revision);
  }

  /**
   * Method testARolledBackMutationAuditsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testARolledBackMutationAuditsNothing(): void
  {
    $equipment = $this->createStub(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment(facilityId: self::FACILITY_ID));
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

    $handler = new PatchCanonicalEquipmentHandler(
      equipment: $equipment,
      facilityValidation: $this->createStub(FacilityValidationPort::class),
      maintenanceLogSynchronizer: $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class),
      interventions: $this->createStub(InterventionScopePort::class),
      eventDispatcher: $dispatcher,
      transactionManager: $transactionManager,
    );

    $handler(new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3, hasStatus: true, status: 'operational'));
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

    $this->handler($equipment)(new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 3));
  }

  /**
   * Method testAMalformedIdentifierIsNotFoundRatherThanInvalid.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierIsNotFoundRatherThanInvalid(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->expects(self::never())->method('findById');

    $this->expectException(EquipmentNotFoundException::class);

    $this->handler($equipment)(new PatchCanonicalEquipmentCommand('not-a-uuid', 3));
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeAnythingIsWritten.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeAnythingIsWritten(): void
  {
    $equipment = $this->createMock(CanonicalEquipmentRepositoryPort::class);
    $equipment->method('findById')->willReturn($this->equipment());
    $equipment->expects(self::never())->method('save');

    $this->expectException(EquipmentRevisionMismatchException::class);

    $this->handler($equipment)(new PatchCanonicalEquipmentCommand(self::EQUIPMENT_ID, 1, hasBrand: true, brand: 'X'));
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?CanonicalEquipmentRepositoryPort $equipment the canonical repository
   * @param ?FacilityValidationPort $facilityValidation the facility ownership check
   * @param ?EquipmentMaintenanceLogSynchronizerPort $synchronizer the maintenance-log synchronizer
   * @param ?InterventionScopePort $interventions the intervention scope port
   * @param ?EventDispatcherPort $eventDispatcher the event dispatcher
   *
   * @return PatchCanonicalEquipmentHandler the handler under test
   */
  private function handler(
    ?CanonicalEquipmentRepositoryPort $equipment = null,
    ?FacilityValidationPort $facilityValidation = null,
    ?EquipmentMaintenanceLogSynchronizerPort $synchronizer = null,
    ?InterventionScopePort $interventions = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): PatchCanonicalEquipmentHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    if (null === $facilityValidation) {
      $facilityValidation = $this->createStub(FacilityValidationPort::class);
      $facilityValidation->method('belongsToOrganization')->willReturn(true);
    }

    return new PatchCanonicalEquipmentHandler(
      equipment: $equipment ?? $this->createStub(CanonicalEquipmentRepositoryPort::class),
      facilityValidation: $facilityValidation,
      maintenanceLogSynchronizer: $synchronizer ?? $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      transactionManager: $transactionManager,
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
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      facilityId: $facilityId,
      type: 'fire_extinguisher',
      subType: null,
      brand: null,
      model: null,
      serialNumber: null,
      locationLabel: null,
      status: $status,
      commissionedAt: null,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
