<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function mb_strlen;
use function preg_match;
use function strtoupper;
use function trim;

/**
 * ValueObject OrganizationVatNumber.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationVatNumber implements Stringable
{
  private const int MIN_LENGTH = 5;

  private const int MAX_LENGTH = 64;

  private const string PATTERN = '/^[A-Z0-9\-\/. ]+$/';

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationVatNumber class.
   *
   * @since 1.0.0
   *
   * @param string $value the VAT number value
   */
  public function __construct(string $value)
  {
    $normalized = strtoupper(trim($value));
    $length = mb_strlen($normalized);

    if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
      throw InvalidValueException::because('Organization VAT number must be between 5 and 64 characters.');
    }

    if (!preg_match(self::PATTERN, $normalized)) {
      throw InvalidValueException::because('Organization VAT number contains unsupported characters.');
    }

    $this->value = $normalized;
  }
  // #endregion

  // #region Methods
  /**
   * Method __toString.
   *
   * Returns the normalized VAT number.
   *
   * @since 1.0.0
   *
   * @return string the VAT number
   */
  public function __toString(): string
  {
    return $this->value;
  }

  /**
   * Method equals.
   *
   * Checks whether two VAT numbers are equal.
   *
   * @since 1.0.0
   *
   * @param self $other the VAT number to compare
   *
   * @return bool true when equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
