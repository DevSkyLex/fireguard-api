<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Factory;

use Equipment\Application\UseCase\Command\Equipment\AssignToFacility\AssignToFacilityResult;
use Equipment\Application\UseCase\Command\Equipment\CommissionEquipment\CommissionEquipmentResult;
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\CreateEquipmentResult;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\DecommissionEquipmentResult;
use Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\PutUnderMaintenanceResult;
use Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility\UnassignFromFacilityResult;
use Equipment\Application\UseCase\Command\Equipment\UpdateEquipment\UpdateEquipmentResult;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Equipment\Presentation\Api\Dto\Output\Equipment\{EquipmentOutput, TagOutput};

use function array_map;

/**
 * Factory EquipmentOutputFactory.
 *
 * Single Result -> EquipmentOutput mapping shared by every equipment
 * processor and provider. The mapping used to be copied per call site, and
 * the copies drifted (`facilityName` was missing from all seven action
 * responses while the detail read carried it); one factory keeps the wire
 * contract identical whichever operation produced the payload.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * Maps a command or query result to the equipment output DTO. The
   * query-only fields (`maintenanceDueStatus`, `planPosition`) keep their
   * defaults on command results, which do not carry them.
   *
   * @since 1.0.0
   *
   * @param AssignToFacilityResult|CommissionEquipmentResult|CreateEquipmentResult|DecommissionEquipmentResult|GetEquipmentResult|PutUnderMaintenanceResult|UnassignFromFacilityResult|UpdateEquipmentResult $view the command/query result view
   *
   * @return EquipmentOutput the mapped output
   */
  public function fromView(
    AssignToFacilityResult|CommissionEquipmentResult|CreateEquipmentResult|DecommissionEquipmentResult|GetEquipmentResult|PutUnderMaintenanceResult|UnassignFromFacilityResult|UpdateEquipmentResult $view,
  ): EquipmentOutput {
    $output = new EquipmentOutput();
    $output->id = $view->equipmentId;
    $output->organizationId = $view->organizationId;
    $output->facilityId = $view->facilityId;
    $output->type = $view->type;
    $output->subType = $view->subType;
    $output->brand = $view->brand;
    $output->model = $view->model;
    $output->serialNumber = $view->serialNumber;
    $output->locationLabel = $view->locationLabel;
    $output->facilityName = $view->facilityName;
    $output->status = $view->status;
    $output->installedAt = $view->installedAt;
    $output->commissionedAt = $view->commissionedAt;
    $output->tags = array_map(TagOutput::fromArray(...), $view->tags);
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    if ($view instanceof GetEquipmentResult) {
      $output->maintenanceDueStatus = $view->maintenanceDueStatus;
      $output->planPosition = $view->planPosition;
    }

    return $output;
  }
  // #endregion
}
