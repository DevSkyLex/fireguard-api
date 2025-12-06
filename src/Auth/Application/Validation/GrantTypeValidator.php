<?php

declare(strict_types=1);

namespace Auth\Application\Validation;

use Auth\Domain\Exception\ValidationException;

/**
 * Service GrantTypeValidator
 * @final
 *
 * Validates OAuth2 grant type requirements.
 *
 * @category Validation
 * @package Auth\Application\Validation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantTypeValidator
{
  //#region Constants
  /**
   * Constant GRANT_CLIENT_CREDENTIALS
   *
   * @var string
   */
  public const string GRANT_CLIENT_CREDENTIALS = 'client_credentials';

  /**
   * Constant GRANT_REFRESH_TOKEN
   *
   * @var string
   */
  public const string GRANT_REFRESH_TOKEN = 'refresh_token';

  /**
   * Constant GRANT_AUTHORIZATION_CODE
   *
   * @var string
   */
  public const string GRANT_AUTHORIZATION_CODE = 'authorization_code';

  /**
   * Constant SUPPORTED_GRANT_TYPES
   *
   * @var list<string>
   */
  public const array SUPPORTED_GRANT_TYPES = [
    self::GRANT_CLIENT_CREDENTIALS,
    self::GRANT_REFRESH_TOKEN,
    self::GRANT_AUTHORIZATION_CODE,
  ];
  //#endregion

  //#region Methods
  /**
   * Method validate
   *
   * Validates the grant type and its required parameters.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $grantType The grant type.
   * @param string|null $refreshToken The refresh token.
   * @param string|null $code The authorization code.
   * @param string|null $redirectUri The redirect URI.
   * @param string|null $codeVerifier The PKCE code verifier.
   *
   * @return void
   *
   * @throws ValidationException If validation fails.
   */
  public function validate(
    string $grantType,
    ?string $refreshToken = null,
    ?string $code = null,
    ?string $redirectUri = null,
    ?string $codeVerifier = null,
  ): void {
    if (!in_array($grantType, self::SUPPORTED_GRANT_TYPES, true)) {
      throw ValidationException::invalidGrantType($grantType);
    }

    match ($grantType) {
      self::GRANT_REFRESH_TOKEN => $this->validateRefreshTokenGrant($refreshToken),
      self::GRANT_AUTHORIZATION_CODE => $this->validateAuthorizationCodeGrant($code, $redirectUri, $codeVerifier),
      default => null,
    };
  }

  /**
   * Method validateRefreshTokenGrant
   *
   * Validates refresh_token grant requirements.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string|null $refreshToken The refresh token.
   *
   * @return void
   *
   * @throws ValidationException If validation fails.
   */
  private function validateRefreshTokenGrant(?string $refreshToken): void
  {
    if ($refreshToken === null || $refreshToken === '') {
      throw ValidationException::missingField('refresh_token');
    }
  }

  /**
   * Method validateAuthorizationCodeGrant
   *
   * Validates authorization_code grant requirements.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string|null $code The authorization code.
   * @param string|null $redirectUri The redirect URI.
   * @param string|null $codeVerifier The PKCE code verifier.
   *
   * @return void
   *
   * @throws ValidationException If validation fails.
   */
  private function validateAuthorizationCodeGrant(
    ?string $code,
    ?string $redirectUri,
    ?string $codeVerifier,
  ): void {
    if ($code === null || $code === '') {
      throw ValidationException::missingField('code');
    }

    if ($redirectUri === null || $redirectUri === '') {
      throw ValidationException::missingField('redirect_uri');
    }

    // PKCE is mandatory in OAuth 2.1
    if ($codeVerifier === null || $codeVerifier === '') {
      throw ValidationException::missingField('code_verifier');
    }
  }
  //#endregion
}
