<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

/**
 * Enum GrantType
 *
 * Represents an OAuth 2.1 grant type.
 * Grant types define how a client obtains an access token.
 *
 * Note: PASSWORD and IMPLICIT grants are deprecated in OAuth 2.1
 * and have been removed for security reasons.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 3.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/draft-ietf-oauth-v2-1-07
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum GrantType: string
{
  //#region Cases
  /**
   * Case AUTHORIZATION_CODE
   *
   * Authorization code grant type.
   * Used for server-side applications with user authentication.
   */
  case AUTHORIZATION_CODE = 'AUTHORIZATION_CODE';

  /**
   * Case CLIENT_CREDENTIALS
   *
   * Client credentials grant type.
   * Used for machine-to-machine authentication.
   */
  case CLIENT_CREDENTIALS = 'CLIENT_CREDENTIALS';

  /**
   * Case REFRESH_TOKEN
   *
   * Refresh token grant type.
   * Used to obtain a new access token using a refresh token.
   */
  case REFRESH_TOKEN = 'REFRESH_TOKEN';
  //#endregion

  //#region Constants
  /**
   * Constant VALUES
   *
   * Array of all possible grant type values.
   * Used for validation in attributes where method calls are not allowed.
   *
   * @var list<string>
   */
  public const array VALUES = [
    'AUTHORIZATION_CODE',
    'CLIENT_CREDENTIALS',
    'REFRESH_TOKEN',
  ];
  //#endregion

  //#region Methods
  /**
   * Method values
   *
   * Returns an array of all possible values.
   * This is a helper method that returns the same as the VALUES constant.
   *
   * @access public
   * @since 2.0.0
   *
   * @return list<string> An array of values.
   */
  public static function values(): array
  {
    return self::VALUES;
  }

  /**
   * Method isAuthorizationCode
   *
   * Checks if this is an authorization code grant.
   *
   * @access public
   * @since 2.0.0
   *
   * @return bool True if authorization code grant, false otherwise.
   */
  public function isAuthorizationCode(): bool
  {
    return $this === self::AUTHORIZATION_CODE;
  }

  /**
   * Method isClientCredentials
   *
   * Checks if this is a client credentials grant.
   *
   * @access public
   * @since 2.0.0
   *
   * @return bool True if client credentials grant, false otherwise.
   */
  public function isClientCredentials(): bool
  {
    return $this === self::CLIENT_CREDENTIALS;
  }

  /**
   * Method isRefreshToken
   *
   * Checks if this is a refresh token grant.
   *
   * @access public
   * @since 2.0.0
   *
   * @return bool True if refresh token grant, false otherwise.
   */
  public function isRefreshToken(): bool
  {
    return $this === self::REFRESH_TOKEN;
  }

  /**
   * Method requiresUserAuthentication
   *
   * Checks if this grant type requires user authentication.
   *
   * @access public
   * @since 2.0.0
   *
   * @return bool True if user authentication is required, false otherwise.
   */
  public function requiresUserAuthentication(): bool
  {
    return match ($this) {
      self::AUTHORIZATION_CODE => true,
      self::CLIENT_CREDENTIALS, self::REFRESH_TOKEN => false,
    };
  }

  /**
   * Method label
   *
   * Returns a human-readable label for the grant type.
   *
   * @access public
   * @since 2.0.0
   *
   * @return string The human-readable label.
   */
  public function label(): string
  {
    return match ($this) {
      self::AUTHORIZATION_CODE => 'Authorization Code',
      self::CLIENT_CREDENTIALS => 'Client Credentials',
      self::REFRESH_TOKEN      => 'Refresh Token',
    };
  }
  //#endregion
}
