<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Explicit primitive transport values retain exact decimal strings and operation UUIDs. */
final class ReturnProcurementReceiptInput
{
  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  public string $quantity = '';

  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  public string $reason = '';

  #[Groups(['procurement:write'])]
  #[Assert\NotBlank]
  #[Assert\Uuid]
  public string $clientOperationId = '';
}
