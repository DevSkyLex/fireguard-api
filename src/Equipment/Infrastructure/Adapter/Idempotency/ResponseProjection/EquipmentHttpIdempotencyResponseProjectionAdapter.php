<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Idempotency\ResponseProjection;

use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\AssignToFacilityCommand;
use Equipment\Application\UseCase\Command\Equipment\CommissionEquipment\CommissionEquipmentCommand;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\DecommissionEquipmentCommand;
use Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\PatchCanonicalEquipmentCommand;
use Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\PutUnderMaintenanceCommand;
use Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition\SetEquipmentPlanPositionCommand;
use Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility\UnassignFromFacilityCommand;
use Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\UpdateEquipmentCommand;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailQuery;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyResponseProjectionPort;

use function in_array;

/**
 * Adapter EquipmentHttpIdempotencyResponseProjectionAdapter.
 *
 * Constructs the exact detail reads used by equipment processors while their owning write remains uncommitted.
 *
 * @category Adapter
 */
final readonly class EquipmentHttpIdempotencyResponseProjectionAdapter implements HttpIdempotencyResponseProjectionPort
{
  // #region Methods
  /**
   * Method supports.
   *
   * @param string $operation the owner-declared HTTP mutation
   *
   * @return bool whether the processor reads equipment detail after its primary command
   */
  public function supports(string $operation): bool
  {
    return in_array($operation, ['equipment_update', 'equipment_assign_to_facility', 'equipment_unassign_from_facility', 'equipment_commission', 'equipment_maintenance', 'equipment_decommission', 'equipment_plan_position_set', 'canonical_equipment_patch'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array
  {
    if (!$this->supports($operation)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if ($command instanceof PatchCanonicalEquipmentCommand) {
      return [new GetEquipmentDetailQuery($command->equipmentId, userId: $command->userId ?? throw new IdempotencyDeniedException('result_unavailable'))];
    }
    if ($command instanceof UpdateEquipmentCommand || $command instanceof AssignToFacilityCommand || $command instanceof UnassignFromFacilityCommand || $command instanceof CommissionEquipmentCommand || $command instanceof PutUnderMaintenanceCommand || $command instanceof DecommissionEquipmentCommand || $command instanceof SetEquipmentPlanPositionCommand) {
      return [new GetEquipmentDetailQuery($command->equipmentId, $command->organizationId)];
    }

    throw new IdempotencyDeniedException('result_unavailable');
  }
  // #endregion
}
