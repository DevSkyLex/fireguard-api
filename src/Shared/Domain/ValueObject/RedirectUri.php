<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

/**
 * ValueObject RedirectUri
 * @final
 *
 * It represents a redirect URI.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RedirectUri implements Stringable
{
  //#region Constants
  /**
   * Constant ALLOWED_SCHEMES
   *
   * The allowed schemes for
   * the redirect URI.
   *
   * @access private
   * @since 1.0.0
   *
   * @var array<string> ALLOWED_SCHEMES
   */
  private const array ALLOWED_SCHEMES = ['https'];
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the RedirectUri class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The redirect URI.
   *
   * @throws InvalidValueException If the redirect URI is invalid.
   */
  public function __construct(public string $value)
  {
    // Validate the redirect URI.
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
      throw InvalidValueException::because(message: 'Invalid redirect URI.');
    }

    // Validate the redirect URI scheme.
    $scheme = parse_url($value, PHP_URL_SCHEME);

    // Check if the redirect URI scheme is supported.
    if ($scheme === null || !in_array(strtolower((string) $scheme), self::ALLOWED_SCHEMES, true)) {
      throw InvalidValueException::because(message: 'Unsupported redirect URI scheme.');
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method equals
   *
   * Compares two RedirectUri objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other RedirectUri object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   *
   * Returns the string representation of the RedirectUri object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the RedirectUri object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
