<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Command\ApplyInventoryStock;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/** @category UseCase */
final readonly class ApplyInventoryStockCommand implements CommandMessage
{
  public function __construct(public string $organizationId, public string $actorId, public string $kind, public ?string $clientOperationId = null, public ?string $partId = null, public ?string $warehouseId = null, public ?string $quantity = null, public ?DateTimeImmutable $occurredAt = null, public ?string $interventionId = null, public ?string $workItemId = null, public ?string $equipmentId = null, public ?string $reason = null, public ?string $unitCost = null, public ?string $currency = null, public ?string $sourceReceiptId = null, public ?string $originalId = null)
  {
  }
}
