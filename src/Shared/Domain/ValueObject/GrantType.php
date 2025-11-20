<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;
use function sprintf;
use function implode;
use function in_array;

/**
 * ValueObject GrantType
 * @final
 *
 * Represents an OAuth 2.0 grant type.
 * Grant types define how a client obtains an access token.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantType implements Stringable
{
  //#region Constants
  /**
   * Constant AUTHORIZATION_CODE
   *
   * Authorization code grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string AUTHORIZATION_CODE
   */
  public const string AUTHORIZATION_CODE = 'authorization_code';

  /**
   * Constant CLIENT_CREDENTIALS
   *
   * Client credentials grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string CLIENT_CREDENTIALS
   */
  public const string CLIENT_CREDENTIALS = 'client_credentials';

  /**
   * Constant REFRESH_TOKEN
   *
   * Refresh token grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string REFRESH_TOKEN
   */
  public const string REFRESH_TOKEN = 'refresh_token';

  /**
   * Constant PASSWORD
   *
   * Password grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string PASSWORD
   */
  public const string PASSWORD = 'password';

  /**
   * Constant IMPLICIT
   *
   * Implicit grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @var string IMPLICIT
   */
  public const string IMPLICIT = 'implicit';

  /**
   * Constant VALID_GRANT_TYPES
   *
   * List of valid OAuth 2.0 grant types.
   * 
   * @access public
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array VALID_GRANT_TYPES = [
    self::AUTHORIZATION_CODE,
    self::CLIENT_CREDENTIALS,
    self::REFRESH_TOKEN,
    self::PASSWORD,
    self::IMPLICIT,
  ];
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the GrantType class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The grant type value.
   *
   * @throws InvalidValueException If the grant type is invalid.
   */
  public function __construct(public string $value)
  {
    if (!in_array(needle: $value, haystack: self::VALID_GRANT_TYPES, strict: true)) {
      throw InvalidValueException::because(
        message: sprintf(
          'Invalid grant type "%s". Must be one of: %s',
          $value,
          implode(separator: ', ', array: self::VALID_GRANT_TYPES)
        )
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method isAuthorizationCode
   *
   * Checks if this is an authorization code grant.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if authorization code grant, false otherwise.
   */
  public function isAuthorizationCode(): bool
  {
    return $this->value === self::AUTHORIZATION_CODE;
  }

  /**
   * Method isClientCredentials
   *
   * Checks if this is a client credentials grant.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if client credentials grant, false otherwise.
   */
  public function isClientCredentials(): bool
  {
    return $this->value === self::CLIENT_CREDENTIALS;
  }

  /**
   * Method isRefreshToken
   *
   * Checks if this is a refresh token grant.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if refresh token grant, false otherwise.
   */
  public function isRefreshToken(): bool
  {
    return $this->value === self::REFRESH_TOKEN;
  }

  /**
   * Method requiresUserAuthentication
   *
   * Checks if this grant type requires user authentication.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if user authentication is required, false otherwise.
   */
  public function requiresUserAuthentication(): bool
  {
    return in_array(
      needle: $this->value,
      haystack: [self::AUTHORIZATION_CODE, self::PASSWORD, self::IMPLICIT],
      strict: true
    );
  }

  /**
   * Method equals
   *
   * Compares two GrantType objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other GrantType object to compare.
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
   * Returns the string representation of the GrantType object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the GrantType object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
