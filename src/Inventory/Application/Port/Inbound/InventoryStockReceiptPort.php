<?php

declare(strict_types=1);

namespace Inventory\Application\Port\Inbound;

use Inventory\Application\Contract\Stock\{InventoryReceiptRequest, InventoryReceiptResult, InventoryReceiptReturnRequest};

/** @category Port */
interface InventoryStockReceiptPort
{
  /**
   * Must participate in the caller's main transaction.
   */
  public function receive(InventoryReceiptRequest $request): InventoryReceiptResult;

  /**
   * Returns never exceed the original receipt or the available quantity.
   */
  public function reverse(InventoryReceiptReturnRequest $request): InventoryReceiptResult;

  /**
   * A blocked supplier return preserves the caller's physical receipt and leaves all stock unchanged.
   */
  public function tryReverse(InventoryReceiptReturnRequest $request): InventoryReceiptResult;
}
