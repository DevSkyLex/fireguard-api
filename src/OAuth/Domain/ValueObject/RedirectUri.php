<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function filter_var;
use function in_array;
use function parse_url;
use function strtolower;

use const FILTER_VALIDATE_URL;
use const PHP_URL_SCHEME;

/**
 * ValueObject RedirectUri.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RedirectUri implements Stringable
{
  // #region Constants
  /**
   * Constant ALLOWED_SCHEMES.
   *
   * The allowed schemes for
   * the redirect URI.
   *
   * @since 1.0.0
   *
   * @var array<string> ALLOWED_SCHEMES
   */
  private const array ALLOWED_SCHEMES = ['https'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the RedirectUri class.
   *
   * @since 1.0.0
   *
   * @param string $value the redirect URI
   *
   * @throws InvalidValueException if the redirect URI is invalid
   */
  public function __construct(public string $value)
  {
    // Validate the redirect URI.
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
      throw InvalidValueException::because(
        message: 'Invalid redirect URI.',
      );
    }

    // Validate the redirect URI scheme.
    $scheme = parse_url(
      url: $value,
      component: PHP_URL_SCHEME,
    );

    // Check if the redirect URI scheme is supported.
    if (null === $scheme || !in_array(strtolower((string) $scheme), self::ALLOWED_SCHEMES, true)) {
      throw InvalidValueException::because(
        message: 'Unsupported redirect URI scheme.',
      );
    }
  }

  /**
   * Method __toString.
   *
   * Returns the string representation
   * of the RedirectUri object.
   *
   * @since 1.0.0
   *
   * @return string the string representation of the RedirectUri object
   */
  public function __toString(): string
  {
    return $this->value;
  }
  // #endregion

  // #region Methods
  /**
   * Method equals.
   *
   * Compares two RedirectUri objects
   * for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other RedirectUri object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }
  // #endregion
}
