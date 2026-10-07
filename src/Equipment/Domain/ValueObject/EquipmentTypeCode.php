<?php

declare(strict_types=1);

namespace Equipment\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function trim;

/**
 * An organization-defined equipment type, preserving historical literals.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentTypeCode
{
  private function __construct(public string $value)
  {
  }

  /**
   * @since 1.0.0
   */
  public static function fromString(string $value): EquipmentType|self
  {
    if ('' === trim($value) || mb_strlen($value) > 32) {
      throw InvalidValueException::because('Equipment type must contain between 1 and 32 characters.');
    }

    return EquipmentType::tryFrom($value) ?? new self($value);
  }

  /**
   * @since 1.0.0
   */
  public function label(): string
  {
    return $this->value;
  }
}
