<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class MaintenanceCostHttpIdempotencyOperationContributionAdapter.
 *
 * Declares every owning main-database mutation and its exact immutable public-field result graph.
 *
 * @category Adapter
 */
final readonly class MaintenanceCostHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
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
        name: 'maintenance_cost_planning',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\WriteMaintenanceCostCommand::class,
        resultClass: \MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\WriteMaintenanceCostResult::class,
        contractClasses: [\MaintenanceCost\Application\Contract\Cost\MaintenanceCostView::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostPlanning::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostTotals::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostSnapshot::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostItem::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'view.interventionId',
        resourceKind: 'maintenance_intervention',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_cost_expense',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\WriteMaintenanceCostCommand::class,
        resultClass: \MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\WriteMaintenanceCostResult::class,
        contractClasses: [\MaintenanceCost\Application\Contract\Cost\MaintenanceCostView::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostPlanning::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostTotals::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostSnapshot::class, \MaintenanceCost\Application\Contract\Cost\MaintenanceCostItem::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'view.interventionId',
        resourceKind: 'maintenance_intervention',
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_cost_currency_configure',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency\ConfigureMaintenanceCurrencyCommand::class,
        resultClass: \MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency\ConfigureMaintenanceCurrencyResult::class,
        contractClasses: [\MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot::class],
        statusCode: 200,
        requiredKey: true,
      ),
      new HttpIdempotencyOperation(
        name: 'maintenance_cost_rates_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate\CreateMaintenanceRateCommand::class,
        resultClass: \MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate\CreateMaintenanceRateResult::class,
        contractClasses: [\MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'rate.id',
        resourceKind: 'maintenance_rate',
      ),
    ];
  }
  // #endregion
}
