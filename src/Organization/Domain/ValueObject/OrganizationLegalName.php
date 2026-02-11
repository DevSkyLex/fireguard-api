<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function trim;

/**
 * ValueObject OrganizationLegalName.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationLegalName implements Stringable
{
  private const int MIN_LENGTH = 2;

  private const int MAX_LENGTH = 160;

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationLegalName class.
   *
   * @since 1.0.0
   *
   * @param string $value the legal name value
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);
    $length = mb_strlen($normalized);

    if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
      throw InvalidValueException::because('Organization legal name must be between 2 and 160 characters.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized legal name.
   *
   * @since 1.0.0
   *
   * @return string the legal name
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two legal names are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the legal name to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
