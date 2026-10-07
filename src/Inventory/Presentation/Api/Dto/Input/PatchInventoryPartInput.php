<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** @category DTO */
final class PatchInventoryPartInput
{
  #[Groups(['inventory:write'])]
  #[Assert\Length(min:1, max:255)]
  public ?string $label = null;

  #[Groups(['inventory:write'])]
  #[Assert\Length(min:1, max:32)]
  public ?string $unit = null;

  #[Groups(['inventory:write'])]
  public ?bool $archived = null;
}
