<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class WorkloadHttpIdempotencyOperationContributionAdapter.
 *
 * Declares every owning main-database mutation and its exact immutable public-field result graph.
 *
 * @category Adapter
 */
final readonly class WorkloadHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
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
        name: 'workload_settings_save',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityCommand::class,
        resultClass: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'workload_week',
      ),
      new HttpIdempotencyOperation(
        name: 'workload_capacity_save',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityCommand::class,
        resultClass: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'workload_week',
      ),
      new HttpIdempotencyOperation(
        name: 'workload_exception_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityCommand::class,
        resultClass: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'workload_exception',
      ),
      new HttpIdempotencyOperation(
        name: 'workload_exception_cancel',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityCommand::class,
        resultClass: \Workload\Application\UseCase\Command\Capacity\ChangeCapacity\ChangeCapacityResult::class,
        statusCode: 204,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'workload_exception',
      ),
    ];
  }
  // #endregion
}
