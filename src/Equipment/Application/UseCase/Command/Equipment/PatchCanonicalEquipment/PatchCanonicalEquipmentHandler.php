<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment;

use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\{CanonicalEquipmentRepositoryPort, FacilityValidationPort, InterventionScopePort};
use Equipment\Domain\Event\Equipment\{EquipmentCommissionedEvent, EquipmentDecommissionedEvent, EquipmentPutUnderMaintenanceEvent, EquipmentReturnedToStockEvent};
use Equipment\Domain\Exception\{CanonicalEquipmentValidationException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\{CanonicalEquipmentPatch, EquipmentId, EquipmentStatus};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase PatchCanonicalEquipmentHandler.
 *
 * Applies one merge patch to the flat `PATCH /api/equipment/{id}` surface.
 *
 * **The validation order is load-bearing.** `type` and `status` are checked
 * for an explicit null first, the facility's organization second, and only
 * then does the model apply the patch and check the in-service and
 * transition rules. That is the order `CanonicalEquipmentMutationProcessor`
 * ran, and therefore the message a client sending several invalid fields at
 * once observes.
 *
 * **The audit event is dispatched only after the transaction commits.** The
 * ledger lives on the `auth` database and commits independently, so
 * dispatching inside the transaction could record a row for a mutation the
 * `main` database then rolls back — a phantom entry in an append-only,
 * hash-chained ledger.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalEquipmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipmentRepositoryPort $equipment the canonical equipment repository
   * @param FacilityValidationPort $facilityValidation the facility ownership check
   * @param EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer the maintenance-log synchronizer
   * @param InterventionScopePort $interventions the intervention scope port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private CanonicalEquipmentRepositoryPort $equipment,
    private FacilityValidationPort $facilityValidation,
    private EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer,
    private InterventionScopePort $interventions,
    private EventDispatcherPort $eventDispatcher,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param PatchCanonicalEquipmentCommand $command the command payload
   *
   * @return PatchCanonicalEquipmentResult the use case result
   */
  public function __invoke(PatchCanonicalEquipmentCommand $command): PatchCanonicalEquipmentResult
  {
    $previousStatus = null;

    /** @var CanonicalEquipment $equipment */
    $equipment = $this->transactionManager->transactional(
      function () use ($command, &$previousStatus): CanonicalEquipment {
        $equipment = $this->equipment->findById($this->identifier($command->equipmentId));
        if (null === $equipment) {
          throw EquipmentNotFoundException::withId($command->equipmentId);
        }

        $equipment->assertRevisionMatches($command->expectedRevision);

        $patch = self::patch($command);
        $patch->assertNonNullableFieldsArePresent();

        if ($patch->hasFacility && null !== $patch->facilityId
          && !$this->facilityValidation->belongsToOrganization($patch->facilityId, (string) $equipment->organizationId())) {
          throw CanonicalEquipmentValidationException::facilityOutsideOrganization();
        }

        $previousStatus = $equipment->applyPatch($patch);

        // Keep the maintenance-log history in step with the transition,
        // mirroring the PutUnderMaintenance / Commission / Decommission
        // handlers. A scratchpad edit never reaches this.
        if (null !== $previousStatus) {
          $this->maintenanceLogSynchronizer->syncForStatusTransition(
            (string) $equipment->id(),
            (string) $equipment->organizationId(),
            $previousStatus,
            $equipment->status()->value,
          );
        }

        $this->equipment->save($equipment);
        $this->interventions->touchDraft($equipment->interventionId());

        return $equipment;
      },
    );

    if (null !== $previousStatus) {
      $this->dispatchStatusChange($equipment, $previousStatus);
    }

    return new PatchCanonicalEquipmentResult(
      equipmentId: (string) $equipment->id(),
      status: $equipment->status()->value,
      revision: $equipment->revision(),
      previousStatus: $previousStatus,
    );
  }

  /**
   * Method patch.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param PatchCanonicalEquipmentCommand $command the command payload
   *
   * @return CanonicalEquipmentPatch the domain patch
   */
  private static function patch(PatchCanonicalEquipmentCommand $command): CanonicalEquipmentPatch
  {
    return new CanonicalEquipmentPatch(
      hasType: $command->hasType,
      type: $command->type,
      hasStatus: $command->hasStatus,
      status: $command->status,
      hasSubType: $command->hasSubType,
      subType: $command->subType,
      hasBrand: $command->hasBrand,
      brand: $command->brand,
      hasModel: $command->hasModel,
      model: $command->model,
      hasSerialNumber: $command->hasSerialNumber,
      serialNumber: $command->serialNumber,
      hasLocationLabel: $command->hasLocationLabel,
      locationLabel: $command->locationLabel,
      hasFacility: $command->hasFacility,
      facilityId: $command->facilityId,
    );
  }

  /**
   * Method dispatchStatusChange.
   *
   * Emits the audit event matching a published-equipment status transition.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipment $equipment the mutated equipment
   * @param string $previousStatus the status before the transition
   */
  private function dispatchStatusChange(CanonicalEquipment $equipment, string $previousStatus): void
  {
    $organizationId = (string) $equipment->organizationId();
    $equipmentId = (string) $equipment->id();

    $event = match ($equipment->status()) {
      EquipmentStatus::OPERATIONAL => new EquipmentCommissionedEvent(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        facilityId: $equipment->facilityId(),
        previousStatus: $previousStatus,
      ),
      EquipmentStatus::UNDER_MAINTENANCE => new EquipmentPutUnderMaintenanceEvent(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        facilityId: $equipment->facilityId(),
        previousStatus: $previousStatus,
      ),
      EquipmentStatus::IN_STOCK => new EquipmentReturnedToStockEvent(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        previousStatus: $previousStatus,
      ),
      EquipmentStatus::DECOMMISSIONED => new EquipmentDecommissionedEvent(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        previousStatus: $previousStatus,
      ),
    };

    $this->eventDispatcher->dispatch($event);
  }

  /**
   * Method identifier.
   *
   * Turns a malformed identifier into the same 404 an unknown one gets — the
   * canonical routes answered 404 for any unparseable id before the
   * identifier became a value object.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the raw equipment identifier
   *
   * @return EquipmentId the parsed identifier
   */
  private function identifier(string $equipmentId): EquipmentId
  {
    try {
      return EquipmentId::fromString($equipmentId);
    } catch (InvalidValueException) {
      throw EquipmentNotFoundException::withId($equipmentId);
    }
  }
  // #endregion
}
