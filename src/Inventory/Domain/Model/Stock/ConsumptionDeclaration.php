<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

use DateTimeImmutable;

/** @category Model */
final readonly class ConsumptionDeclaration
{
  public function __construct(public string $id, public string $organizationId, public string $partId, public string $warehouseId, public string $quantity, public string $interventionId, public ?string $workItemId, public ?string $equipmentId, public string $actorId, public DateTimeImmutable $occurredAt, public string $status, public ?string $reason, public ?string $movementId, public bool $late = false)
  {
  }
}
