<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** @category DTO */
final class InventoryPartOutput
{
  #[Groups(['inventory:read'])]
  #[ApiProperty(identifier:true)]
  public string $id = '';

  #[Groups(['inventory:read'])]
  public string $code = '';

  #[Groups(['inventory:read'])]
  public string $label = '';

  #[Groups(['inventory:read'])]
  public string $unit = '';

  #[Groups(['inventory:read'])]
  public string $kind = '';

  #[Groups(['inventory:read'])]
  public bool $archived = false;
}
