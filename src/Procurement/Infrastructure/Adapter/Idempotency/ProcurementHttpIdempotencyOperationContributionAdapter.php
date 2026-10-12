<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class ProcurementHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class ProcurementHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'procurement_reconcile_return',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'return',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_create_supplier',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'supplier',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_change_supplier',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'supplier',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_archive_supplier',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'supplier',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_create_order',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'purchase_order',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_change_order',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'purchase_order',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_place_order',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'purchase_order',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_cancel_remaining',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'purchase_order',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_receive',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'receipt',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_individualize',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'receipt',
      ),
      new HttpIdempotencyOperation(
        name: 'procurement_return',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementCommand::class,
        resultClass: \Procurement\Application\UseCase\Command\ManageProcurement\ManageProcurementResult::class,
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'data.id',
        resourceKind: 'receipt',
      ),
    ];
  }
  // #endregion
}
