<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** @category DTO */
final class InventoryMovementOutput
{
  #[Groups(['inventory:read'])]
  #[ApiProperty(identifier:true)]
  public string $id = '';

  #[Groups(['inventory:read'])]
  public string $partId = '';

  #[Groups(['inventory:read'])]
  public string $warehouseId = '';

  #[Groups(['inventory:read'])]
  public string $kind = '';

  #[Groups(['inventory:read'])]
  public string $quantity = '';

  #[Groups(['inventory:read'])]
  public string $reason = '';

  #[Groups(['inventory:read'])]
  public string $actorId = '';

  #[Groups(['inventory:read'])]
  public string $occurredAt = '';

  #[Groups(['inventory:read'])]
  public ?string $interventionId = null;

  #[Groups(['inventory:read'])]
  public ?string $workItemId = null;

  #[Groups(['inventory:read'])]
  public ?string $equipmentId = null;

  #[Groups(['inventory:read'])]
  public ?string $correctionOf = null;

  #[Groups(['inventory:read'])]
  public ?string $sourceReceiptId = null;

  #[Groups(['inventory:read'])]
  public bool $late = false;

  #[Groups(['inventory:read'])]
  public bool $replayed = false;

  #[Groups(['inventory:read'])]
  public ?InventoryValuationOutput $valuation = null;
}
