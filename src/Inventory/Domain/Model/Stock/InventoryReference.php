<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

use InvalidArgumentException;

use function in_array;
use function mb_strlen;
use function trim;

/** @category Model */
final readonly class InventoryReference
{
  public function __construct(public string $id, public string $organizationId, public string $code, public string $label, public ?string $unit = null, public ?string $kind = null, public bool $archived = false)
  {
    if ('' === trim($code) || mb_strlen($code) > 100 || '' === trim($label) || mb_strlen($label) > 255 || (null !== $unit && ('' === trim($unit) || mb_strlen($unit) > 32)) || (null !== $kind && !in_array($kind, ['part', 'consumable'], true))) {
      throw new InvalidArgumentException('Invalid inventory reference.');
    }
  }
}
