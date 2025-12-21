<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Stringable;

/**
 * ValueObject TenantId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantId implements Stringable
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the TenantId class.
   *
   * @since 1.0.0
   *
   * @param Uuid $uuid the UUID
   */
  public function __construct(private Uuid $uuid)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromString.
   *
   * Creates a new TenantId from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the string value
   *
   * @return self the created TenantId
   */
  public static function fromString(string $value): self
  {
    return new self(uuid: new Uuid(
      value: $value
    ));
  }

  /**
   * Method equals.
   *
   * Compares two TenantId objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other TenantId object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->uuid->equals(other: $other->uuid);
  }

  /**
   * Method toUuid.
   *
   * Returns the UUID.
   *
   * @since 1.0.0
   *
   * @return Uuid the UUID
   */
  public function toUuid(): Uuid
  {
    return $this->uuid;
  }

  /**
   * Method __toString.
   *
   * Returns the string representation of the TenantId object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the TenantId object
   */
  public function __toString(): string
  {
    return (string) $this->uuid;
  }
  // #endregion
}
