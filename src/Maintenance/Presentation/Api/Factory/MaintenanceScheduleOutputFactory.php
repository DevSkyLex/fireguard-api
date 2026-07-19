<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Factory;

use Maintenance\Application\Contract\Schedule\MaintenanceScheduleView;
use Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput;

/**
 * Factory MaintenanceScheduleOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceScheduleOutputFactory
{
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleView $view the view value
   *
   * @return MaintenanceScheduleOutput the from view result
   */
  public function fromView(MaintenanceScheduleView $view): MaintenanceScheduleOutput
  {
    $output = new MaintenanceScheduleOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->equipment = '/api/equipment/' . $view->equipmentId;
    $output->facility = null === $view->facilityId ? null : '/api/facilities/' . $view->facilityId;
    $output->equipmentType = $view->equipmentType;
    $output->intervalOverride = $view->intervalOverride;
    $output->lastInspectionClosedAt = $view->lastInspectionClosedAt?->format('c');
    $output->nextDueAt = $view->nextDueAt?->format('c');
    $output->dueStatus = $view->dueStatus;
    $output->lastRemindedAt = $view->lastRemindedAt?->format('c');
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }
}
