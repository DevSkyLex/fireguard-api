<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function preg_match;
use function trim;

/**
 * ValueObject PlanKey.
 *
 * Stable machine identifier of a subscription plan (for example `free`, `pro`,
 * `max`). Unique across the catalog and safe to reference in code.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanKey implements Stringable
{
  private const int MIN_LENGTH = 2;

  private const int MAX_LENGTH = 60;

  private const string PATTERN = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PlanKey class.
   *
   * @since 1.0.0
   *
   * @param string $value the plan key value
   */
  public function __construct(string $value)
  {
    $normalized = trim($value);
    $length = mb_strlen($normalized);

    if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
      throw InvalidValueException::because('Plan key must be between 2 and 60 characters.');
    }

    if (1 !== preg_match(self::PATTERN, $normalized)) {
      throw InvalidValueException::because('Plan key must contain lowercase letters, numbers and single separators.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized plan key value.
   *
   * @since 1.0.0
   *
   * @return string the plan key value
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two plan keys are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the plan key to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
