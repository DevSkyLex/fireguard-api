<?php

declare(strict_types=1);

namespace Inventory\Domain\ValueObject;

use InvalidArgumentException;

use function explode;
use function preg_match;
use function str_pad;

/** @category ValueObject */
final readonly class StockQuantity
{
  public string $value;

  public function __construct(string $value, bool $signed = false)
  {
    if (1 !== preg_match($signed ? '/^-?(?:0|[1-9][0-9]{0,17})(?:\\.[0-9]{1,6})?$/D' : '/^(?:0|[1-9][0-9]{0,17})(?:\\.[0-9]{1,6})?$/D', $value)) {
      throw new InvalidArgumentException('Quantity must be an exact decimal with at most six fractional digits.');
    }
    [$whole,$fraction] = explode('.', $value, 2) + [1 => ''];
    $normalized = $whole . '.' . str_pad($fraction, 6, '0');
    if ('0.000000' === $normalized || '-0.000000' === $normalized) {
      throw new InvalidArgumentException('Quantity must be nonzero.');
    }
    $this->value = $normalized;
  }
}
