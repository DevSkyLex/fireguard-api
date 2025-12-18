<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject;

use OAuth\Domain\Exception\InvalidOAuthClientIdentifierException;
use Stringable;

/**
 * ValueObject OAuthClientIdentifier
 * @final
 *
 * Represents an OAuth 2.0 client identifier (client_id).
 * This is the public identifier used in OAuth 2.0 flows,
 * distinct from the internal UUID-based ClientId in the Client bounded context.
 *
 * @category ValueObject
 * @package OAuth\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OAuthClientIdentifier implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   *
   * The pattern used to validate the OAuth client identifier.
   * Must be 3-128 characters long, start with alphanumeric,
   * and contain only alphanumeric, dots, hyphens, and underscores.
   *
   * @access private
   * @since 1.0.0
   *
   * @var string PATTERN
   */
  private const string PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,127}$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * OAuthClientIdentifier class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The OAuth client identifier.
   *
   * @throws InvalidOAuthClientIdentifierException If the client identifier is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '') {
      throw InvalidOAuthClientIdentifierException::empty();
    }

    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidOAuthClientIdentifierException::invalidPattern(
        value: $value
      );
    }
  }

  /**
   * Method equals
   *
   * Compares two OAuthClientIdentifier 
   * objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other OAuthClientIdentifier object to compare.
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
   * Returns the string representation of the 
   * OAuthClientIdentifier object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the OAuthClientIdentifier object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
