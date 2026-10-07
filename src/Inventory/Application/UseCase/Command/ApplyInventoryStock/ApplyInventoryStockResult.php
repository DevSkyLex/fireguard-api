<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Command\ApplyInventoryStock;

use Inventory\Application\Contract\Stock\InventoryReceiptResult;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration,StockMovement};

/** @category UseCase */
final readonly class ApplyInventoryStockResult implements \Shared\Application\Message\ResultMessage
{
  public function __construct(public ?ConsumptionDeclaration $declaration = null, public ?StockMovement $movement = null, public ?InventoryReceiptResult $receipt = null, public bool $replayed = false)
  {
  }
}
