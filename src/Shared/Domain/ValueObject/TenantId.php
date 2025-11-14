<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Stringable;

/**
 * ValueObject TenantId
 * @final
 *
 * It represents a TenantId.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantId implements Stringable
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the TenantId class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $uuid The UUID.
   */
  public function __construct(private Uuid $uuid) {}
  //#endregion

  //#region Methods
  /**
   * Method fromString
   *
   * Creates a new TenantId from a string.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The string value.
   *
   * @return self The created TenantId.
   */
  public static function fromString(string $value): self
  {
    return new self(uuid: new Uuid(
      value: $value
    ));
  }

  /**
   * Method equals
   *
   * Compares two TenantId objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other TenantId object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->uuid->equals(other: $other->uuid);
  }

  /**
   * Method toUuid
   *
   * Returns the UUID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Uuid The UUID.
   */
  public function toUuid(): Uuid
  {
    return $this->uuid;
  }

  /**
   * Method __toString
   *
   * Returns the string representation of the TenantId object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the TenantId object.
   */
  public function __toString(): string
  {
    return (string) $this->uuid;
  }
  //#endregion
}
