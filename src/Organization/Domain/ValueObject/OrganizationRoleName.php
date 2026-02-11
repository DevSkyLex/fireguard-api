<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function preg_match;

/**
 * ValueObject OrganizationRoleName.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleName implements Stringable
{
  private const string PATTERN = '/^[a-z0-9_]{3,50}$/';

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationRoleName class.
   *
   * @since 1.0.0
   *
   * @param string $value the role name value
   */
  public function __construct(public string $value)
  {
    if (!preg_match(self::PATTERN, $value)) {
      throw InvalidValueException::because(
        'Role name must be 3-50 chars, lowercase alphanumeric or underscore.',
      );
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized role name.
   *
   * @since 1.0.0
   *
   * @return string the role name
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two role names are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the role name to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
