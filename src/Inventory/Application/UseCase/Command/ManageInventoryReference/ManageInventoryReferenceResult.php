<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Command\ManageInventoryReference;

use Inventory\Domain\Model\Stock\InventoryReference;

/** @category UseCase */
final readonly class ManageInventoryReferenceResult implements \Shared\Application\Message\ResultMessage
{
  public function __construct(public InventoryReference $reference)
  {
  }
}
