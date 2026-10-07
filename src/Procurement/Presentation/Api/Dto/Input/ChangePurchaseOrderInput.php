<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Explicit primitive transport values retain exact decimal strings and operation UUIDs. */
final class ChangePurchaseOrderInput
{
  #[Groups(['procurement:write'])]
  public ?string $name = null;

  #[Groups(['procurement:write'])]
  #[Assert\Uuid]
  public ?string $supplierId = null;

  /**
   * @var list<array<string,mixed>>|null
   */
  #[Groups(['procurement:write'])]
  public ?array $lines = null;
}
