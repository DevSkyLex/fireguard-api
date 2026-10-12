<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class MaintenanceExportHttpIdempotencyOperationContributionAdapter.
 *
 * Declares every owning main-database mutation and its exact immutable public-field result graph.
 *
 * @category Adapter
 */
final readonly class MaintenanceExportHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions.
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> explicit HTTP mutations; pure planning assessments remain reads
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'create_maintenance_export',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportCommand::class,
        resultClass: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'maintenance_export',
      ),
      new HttpIdempotencyOperation(
        name: 'adjust_maintenance_export',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportCommand::class,
        resultClass: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'maintenance_export',
      ),
      new HttpIdempotencyOperation(
        name: 'confirm_maintenance_export',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportCommand::class,
        resultClass: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'maintenance_export',
      ),
      new HttpIdempotencyOperation(
        name: 'write_maintenance_export_reference',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportCommand::class,
        resultClass: \MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport\ManageMaintenanceExportResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'maintenance_reference',
      ),
    ];
  }
  // #endregion
}
