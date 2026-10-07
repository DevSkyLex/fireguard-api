<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** @category DTO */
final class DeclareInventoryConsumptionInput
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
  #[Assert\Uuid()]
  public string $interventionId = '';

  #[Groups(['inventory:write'])]
  #[Assert\Uuid()]
  public ?string $workItemId = null;

  #[Groups(['inventory:write'])]
  #[Assert\Uuid()]
  public ?string $equipmentId = null;

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Regex(pattern: '/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.[0-9]{1,6})?(?:Z|[+-]\\d{2}:\\d{2})$/D')]
  public string $occurredAt = '';
}
