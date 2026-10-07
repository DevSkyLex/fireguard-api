<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Adapter\Procurement;

use Inventory\Application\Contract\Stock\{InventoryReceiptRequest, InventoryReceiptResult, InventoryReceiptReturnRequest};
use Inventory\Application\Port\Inbound\InventoryStockReceiptPort;
use Inventory\Application\UseCase\Command\ApplyInventoryStock\{ApplyInventoryStockCommand,ApplyInventoryStockResult};
use LogicException;
use Shared\Application\Port\Inbound\CommandBusPort;

/** Procurement facts use the stock command in the caller's main transaction. @category Adapter */
final readonly class InventoryStockReceiptAdapter implements InventoryStockReceiptPort
{
  public function __construct(private CommandBusPort $commands)
  {
  }

  public function receive(InventoryReceiptRequest $r): InventoryReceiptResult
  {
    /** @var ApplyInventoryStockResult $result */
    $result = $this->commands->dispatch(new ApplyInventoryStockCommand($r->organizationId, $r->actorId, 'receipt', $r->clientOperationId, $r->partId, $r->warehouseId, $r->quantity, unitCost:$r->unitCost, currency:$r->currency, sourceReceiptId:$r->sourceReceiptId));

    return $result->receipt ?? throw new LogicException('Inventory receipt result unavailable.');
  }

  public function reverse(InventoryReceiptReturnRequest $r): InventoryReceiptResult
  {
    $result = $this->tryReverse($r);
    if (null !== $result->blockedReason) {
      throw new \Inventory\Domain\Exception\InventoryConflictException('Insufficient stock for the full supplier return.');
    }

    return $result;
  }

  public function tryReverse(InventoryReceiptReturnRequest $r): InventoryReceiptResult
  {
    /** @var ApplyInventoryStockResult $result */
    $result = $this->commands->dispatch(new ApplyInventoryStockCommand($r->organizationId, $r->actorId, 'receipt_return', $r->clientOperationId, quantity:$r->quantity, reason:$r->reason, originalId:$r->movementId));

    return $result->receipt ?? throw new LogicException('Inventory return result unavailable.');
  }
}
