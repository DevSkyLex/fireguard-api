<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment;

use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\{CanonicalEquipmentRepositoryPort, InterventionScopePort};
use Equipment\Domain\Event\Equipment\EquipmentDecommissionedEvent;
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\CanonicalEquipment;
use Equipment\Domain\ValueObject\EquipmentId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteCanonicalEquipmentHandler.
 *
 * The canonical DELETE contract — uniform across the facility / equipment /
 * inspection flat surfaces: a **draft scratchpad** row is hard-deleted, a
 * **published** one retires to `decommissioned` (TERMINAL and never
 * reversible, unlike the inspection surface's `cancelled`), and a repeat
 * DELETE is an idempotent no-op.
 *
 * Retiring an asset that was under maintenance closes its still-open
 * maintenance log, mirroring `DecommissionEquipmentHandler`. The audit event
 * is dispatched only after the transaction commits.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalEquipmentHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CanonicalEquipmentRepositoryPort $equipment the canonical equipment repository
   * @param EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer the maintenance-log synchronizer
   * @param InterventionScopePort $interventions the intervention scope port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private CanonicalEquipmentRepositoryPort $equipment,
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
   * @param DeleteCanonicalEquipmentCommand $command the command payload
   *
   * @return DeleteCanonicalEquipmentResult the use case result
   */
  public function __invoke(DeleteCanonicalEquipmentCommand $command): DeleteCanonicalEquipmentResult
  {
    $hardDeleted = false;
    $previousStatus = null;

    /** @var CanonicalEquipment $equipment */
    $equipment = $this->transactionManager->transactional(
      function () use ($command, &$hardDeleted, &$previousStatus): CanonicalEquipment {
        $equipment = $this->equipment->findById($this->identifier($command->equipmentId));
        if (null === $equipment) {
          throw EquipmentNotFoundException::withId($command->equipmentId);
        }

        $equipment->assertRevisionMatches($command->expectedRevision);

        if ($equipment->isScratchpad()) {
          $this->equipment->delete($equipment->id());
          $hardDeleted = true;
        } else {
          $previousStatus = $equipment->decommission();
          if (null !== $previousStatus) {
            $this->maintenanceLogSynchronizer->syncForStatusTransition(
              (string) $equipment->id(),
              (string) $equipment->organizationId(),
              $previousStatus,
              $equipment->status()->value,
            );
            $this->equipment->save($equipment);
          }
        }

        $this->interventions->touchDraft($equipment->interventionId());

        return $equipment;
      },
    );

    // A draft hard-delete and an idempotent repeat DELETE both leave the
    // ledger alone: nothing about a real asset changed.
    if (null !== $previousStatus) {
      $this->eventDispatcher->dispatch(new EquipmentDecommissionedEvent(
        organizationId: (string) $equipment->organizationId(),
        equipmentId: (string) $equipment->id(),
        previousStatus: $previousStatus,
      ));
    }

    return new DeleteCanonicalEquipmentResult(
      equipmentId: (string) $equipment->id(),
      hardDeleted: $hardDeleted,
      previousStatus: $previousStatus,
    );
  }

  /**
   * Method identifier.
   *
   * Turns a malformed identifier into the same 404 an unknown one gets.
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
