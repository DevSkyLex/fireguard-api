<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class InventoryHttpIdempotencyOperationContributionAdapter.
 *
 * Declares every owning main-database mutation and its exact immutable public-field result graph.
 *
 * @category Adapter
 */
final readonly class InventoryHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
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
        name: 'inventory_parts_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\InventoryReference::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'reference.id',
        resourceKind: 'inventory_part',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_parts_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\InventoryReference::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'reference.id',
        resourceKind: 'inventory_part',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_warehouses_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\InventoryReference::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'reference.id',
        resourceKind: 'inventory_warehouse',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_warehouses_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ManageInventoryReference\ManageInventoryReferenceResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\InventoryReference::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'reference.id',
        resourceKind: 'inventory_warehouse',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_consumptions_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\ConsumptionDeclaration::class, \Inventory\Domain\Model\Stock\StockMovement::class, \Inventory\Application\Contract\Stock\InventoryReceiptResult::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'declaration.id',
        resourceKind: 'inventory_consumption',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_consumptions_reconcile',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\ConsumptionDeclaration::class, \Inventory\Domain\Model\Stock\StockMovement::class, \Inventory\Application\Contract\Stock\InventoryReceiptResult::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'declaration.id',
        resourceKind: 'inventory_consumption',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_returns_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\ConsumptionDeclaration::class, \Inventory\Domain\Model\Stock\StockMovement::class, \Inventory\Application\Contract\Stock\InventoryReceiptResult::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'movement.id',
        resourceKind: 'inventory_movement',
      ),
      new HttpIdempotencyOperation(
        name: 'inventory_corrections_create',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockCommand::class,
        resultClass: \Inventory\Application\UseCase\Command\ApplyInventoryStock\ApplyInventoryStockResult::class,
        contractClasses: [\Inventory\Domain\Model\Stock\ConsumptionDeclaration::class, \Inventory\Domain\Model\Stock\StockMovement::class, \Inventory\Application\Contract\Stock\InventoryReceiptResult::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'movement.id',
        resourceKind: 'inventory_movement',
      ),
    ];
  }
  // #endregion
}
