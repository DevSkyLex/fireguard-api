<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\ApprovalActionExecutorPort;
use Approval\Domain\Exception\DeferredActionNoLongerApplicableException;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\DecommissionEquipmentCommand;
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Adapter EquipmentDecommissionExecutorAdapter.
 *
 * Tagged `approval.deferred_action_executor` (action type
 * `equipment_decommission`): re-dispatches the EXISTING
 * `DecommissionEquipmentCommand` so the original handler re-validates the
 * equipment's current state. `EquipmentAlreadyDecommissionedException` is
 * unambiguous — decommissioned is a single terminal status — so it always
 * means the deferred action was already applied (a routine idempotent
 * outcome, e.g. a second approve attempt); any other conflict (equipment no
 * longer found) means the deferred action can no longer be applied.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentDecommissionExecutorAdapter implements ApprovalActionExecutorPort
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  public function actionType(): string
  {
    return ApprovalActionTypes::EQUIPMENT_DECOMMISSION;
  }

  public function execute(DeferredActionContext $context): void
  {
    try {
      $this->commandBus->dispatch(new DecommissionEquipmentCommand(
        organizationId: $context->organizationId,
        equipmentId: $context->subjectId,
      ));
    } catch (MessengerRuntimeException $exception) {
      if ($this->findException($exception, EquipmentAlreadyDecommissionedException::class) instanceof EquipmentAlreadyDecommissionedException) {
        return;
      }

      $notFound = $this->findException($exception, EquipmentNotFoundException::class);

      if ($notFound instanceof EquipmentNotFoundException) {
        throw DeferredActionNoLongerApplicableException::becauseSubjectChanged($notFound->getMessage());
      }

      throw $exception;
    }
  }
  // #endregion
}
