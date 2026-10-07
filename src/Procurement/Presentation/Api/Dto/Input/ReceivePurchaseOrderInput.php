<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Explicit primitive transport values retain exact decimal strings and operation UUIDs. */
final class ReceivePurchaseOrderInput
{
  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $lineId = '';

  #[Groups(['procurement:write'])]
  #[Assert\Uuid]
  public ?string $warehouseId = null;

  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  public string $quantity = '';

  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  public string $receivedAt = '';

  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $clientOperationId = '';
}
