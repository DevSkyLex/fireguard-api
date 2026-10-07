<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** @category DTO */
final class CorrectInventoryStockInput
{
  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Uuid()]
  public string $clientOperationId = '';

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Uuid()]
  public string $partId = '';

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Uuid()]
  public string $warehouseId = '';

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  public string $quantity = '';

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max:2000)]
  public string $reason = '';

  #[Groups(['inventory:write'])]
  public ?string $unitCost = null;
}
