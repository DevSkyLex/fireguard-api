<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class MaintenanceHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 */
final readonly class MaintenanceHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'maintenance_schedule_export',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport\PrepareMaintenanceScheduleExportCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport\PrepareMaintenanceScheduleExportResult::class,
        statusCode: 200,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: '_api_/maintenance/campaigns_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\GenerateInspectionCampaignCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Campaign\GenerateInspectionCampaign\GenerateInspectionCampaignResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'interventionId',
        resourceKind: 'intervention',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_plan_prepare_legacy',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanResult::class,
        contractClasses: [\Maintenance\Application\Contract\Plan\MaintenancePlanDetails::class, \Maintenance\Application\Contract\Plan\MaintenancePlanSnapshot::class, \Maintenance\Application\Contract\Plan\MaintenanceOccurrenceSnapshot::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'maintenance_plan',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_plan_activate',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanResult::class,
        contractClasses: [\Maintenance\Application\Contract\Plan\MaintenancePlanDetails::class, \Maintenance\Application\Contract\Plan\MaintenancePlanSnapshot::class, \Maintenance\Application\Contract\Plan\MaintenanceOccurrenceSnapshot::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'maintenance_plan',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_plan_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanResult::class,
        contractClasses: [\Maintenance\Application\Contract\Plan\MaintenancePlanDetails::class, \Maintenance\Application\Contract\Plan\MaintenancePlanSnapshot::class, \Maintenance\Application\Contract\Plan\MaintenanceOccurrenceSnapshot::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'details.plan.id',
        resourceKind: 'maintenance_plan',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_plan_update',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanResult::class,
        contractClasses: [\Maintenance\Application\Contract\Plan\MaintenancePlanDetails::class, \Maintenance\Application\Contract\Plan\MaintenancePlanSnapshot::class, \Maintenance\Application\Contract\Plan\MaintenanceOccurrenceSnapshot::class],
        statusCode: 200,
        requiredKey: true,
        resourceKind: 'maintenance_plan',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_plan_archive',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanResult::class,
        contractClasses: [\Maintenance\Application\Contract\Plan\MaintenancePlanDetails::class, \Maintenance\Application\Contract\Plan\MaintenancePlanSnapshot::class, \Maintenance\Application\Contract\Plan\MaintenanceOccurrenceSnapshot::class],
        statusCode: 204,
        requiredKey: true,
        resourceKind: 'maintenance_plan',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_plan_generate',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Plan\ManageMaintenancePlan\ManageMaintenancePlanResult::class,
        contractClasses: [\Maintenance\Application\Contract\Plan\MaintenancePlanDetails::class, \Maintenance\Application\Contract\Plan\MaintenancePlanSnapshot::class, \Maintenance\Application\Contract\Plan\MaintenanceOccurrenceSnapshot::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'interventionId',
        resourceKind: 'intervention',
        resultIdentifierNullable: true,
      ),
      new HttpIdempotencyOperation(
        name: '_api_/maintenance/schedules/{id}_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\SetMaintenanceScheduleOverrideCommand::class,
        resultClass: \Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\SetMaintenanceScheduleOverrideResult::class,
        contractClasses: [\Maintenance\Application\Contract\Schedule\MaintenanceScheduleView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'schedule.id',
        resourceKind: 'maintenance_schedule',
      ),
    ];
  }
  // #endregion
}
