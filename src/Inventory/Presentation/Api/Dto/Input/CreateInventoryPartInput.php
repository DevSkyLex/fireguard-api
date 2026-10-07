<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** @category DTO */
final class CreateInventoryPartInput
{
  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max:100)]
  public string $code = '';

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max:255)]
  public string $label = '';

  #[Groups(['inventory:write'])]
  #[Assert\NotBlank()]
  #[Assert\Length(max:32)]
  public string $unit = 'piece';

  #[Groups(['inventory:write'])]
  #[Assert\Choice(choices:['part', 'consumable'])]
  public string $kind = 'part';
}
