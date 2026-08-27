<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Operation;

/**
 * Operation MaintenanceOperations.
 *
 * Typed API Platform operation name constants for the Maintenance module.
 * The pre-existing list/get/patch/campaign operations on
 * `MaintenanceScheduleResource`/`MaintenanceCampaignResource` carry no
 * explicit `name:` (API Platform derives one) and are left untouched; this
 * constant is introduced for the export operation only, per the
 * `api-platform-contract` skill's checklist item — mirrors
 * `Intervention\...\InterventionOperations::EXPORT_INTERVENTIONS`.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceOperations
{
  // #region Constants
  /**
   * Constant EXPORT_MAINTENANCE_SCHEDULES.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string EXPORT_MAINTENANCE_SCHEDULES = 'maintenance_schedule_export';
  // #endregion
}
