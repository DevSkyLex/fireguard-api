<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function preg_match;
use function strtoupper;
use function trim;

/**
 * ValueObject OrganizationCountryCode.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCountryCode implements Stringable
{
  private const string DEFAULT_COUNTRY_CODE = 'FR';

  private const string PATTERN = '/^[A-Z]{2}$/';

  // #region Properties
  private string $value;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationCountryCode class.
   *
   * @since 1.0.0
   *
   * @param string $value the country code
   */
  public function __construct(string $value)
  {
    $normalized = strtoupper(trim($value));
    if ('' === $normalized || !preg_match(self::PATTERN, $normalized)) {
      throw InvalidValueException::because('Organization country code must be a valid ISO 3166-1 alpha-2 value.');
    }

    $this->value = $normalized;
  }

  /**
   * Method __toString.
   *
   * @since 1.0.0
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion

  // #region Methods
  /**
   * Method fromNullable.
   *
   * Creates a country code from an optional value.
   *
   * @since 1.0.0
   *
   * @param ?string $value the optional country code
   *
   * @return self the normalized country code
   */
  public static function fromNullable(?string $value): self
  {
    return new self(null === $value ? self::DEFAULT_COUNTRY_CODE : $value);
  }

  /**
   * Method default.
   *
   * Returns the default country code used when none is provided.
   *
   * @since 1.0.0
   *
   * @return self the default country code
   */
  public static function default(): self
  {
    return new self(self::DEFAULT_COUNTRY_CODE);
  }

  /**
   * Method equals.
   *
   * @since 1.0.0
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
