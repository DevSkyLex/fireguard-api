<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function preg_match;
use function trim;

/**
 * ValueObject TeamName.
 *
 * A team display name (spaces allowed). Unlike {@see OrganizationRoleName},
 * this is not an RBAC role slug: it is free-form text shown in the UI, so it
 * only enforces a trimmed length bound and rejects control characters.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamName implements Stringable
{
  // #region Constants
  private const string CONTROL_CHARS_PATTERN = '/[\x00-\x1F\x7F]/';
  // #endregion

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the TeamName class.
   *
   * @since 1.0.0
   *
   * @param string $value the raw team name
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);

    if ('' === $normalized) {
      throw InvalidValueException::because('Team name cannot be empty.');
    }

    $length = mb_strlen($normalized);
    if ($length < 2 || $length > 80) {
      throw InvalidValueException::because('Team name must be between 2 and 80 characters.');
    }

    if (1 === preg_match(self::CONTROL_CHARS_PATTERN, $normalized)) {
      throw InvalidValueException::because('Team name cannot contain control characters.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized team name.
   *
   * @since 1.0.0
   *
   * @return string the normalized team name
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two team names are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the name to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
