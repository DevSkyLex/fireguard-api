<?php

declare(strict_types=1);

namespace Tenant\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject TenantName
 * @final
 *
 * Represents a tenant name.
 *
 * @category ValueObject
 * @package Tenant\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TenantName
{
  //#region Constants
  /**
   * Constant MIN_LENGTH
   *
   * Minimum length for a tenant name.
   *
   * @access private
   * @since 1.0.0
   *
   * @var int
   */
  private const int MIN_LENGTH = 2;
  /**
   * Constant MAX_LENGTH
   *
   * Maximum length for a tenant name.
   *
   * @access private
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_LENGTH = 100;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The tenant name value.
   *
   * @throws InvalidValueException If the name is invalid.
   */
  public function __construct(
    public string $value
  ) {
    $length = mb_strlen($value);

    if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
      throw InvalidValueException::because(
        sprintf(
          'Tenant name must be between %d and %d characters.',
          self::MIN_LENGTH,
          self::MAX_LENGTH
        )
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method __toString
   *
   * Returns the string representation.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The tenant name.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
