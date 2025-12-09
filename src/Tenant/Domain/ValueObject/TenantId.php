<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject TenantId
 * @final
 *
 * Represents a unique tenant identifier.
 * Inherits UUID v4 generation from parent via Late Static Binding.
 *
 * @category ValueObject
 * @package Tenant\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantId extends Uuid
{
  //#region Methods
  /**
   * Method fromString
   * @static
   *
   * Creates a TenantId from a string value.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The UUID string.
   *
   * @return self The created TenantId.
   */
  public static function fromString(string $value): self
  {
    return new self(value: $value);
  }
  //#endregion
}
