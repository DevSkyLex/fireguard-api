<?php

declare(strict_types=1);

namespace Auth\Domain\Service;

use Auth\Domain\Model\AccessToken;
use Auth\Domain\Model\RefreshToken;

/**
 * Service TokenValidationService
 * @final
 *
 * Domain service for token validation logic.
 *
 * @category Service
 * @package Auth\Domain\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenValidationService
{
  //#region Constants
  /**
   * Constant VALIDATION_OK
   *
   * @var string
   */
  public const string VALIDATION_OK = 'ok';

  /**
   * Constant VALIDATION_EXPIRED
   *
   * @var string
   */
  public const string VALIDATION_EXPIRED = 'expired';

  /**
   * Constant VALIDATION_REVOKED
   *
   * @var string
   */
  public const string VALIDATION_REVOKED = 'revoked';

  /**
   * Constant VALIDATION_NOT_FOUND
   *
   * @var string
   */
  public const string VALIDATION_NOT_FOUND = 'not_found';

  /**
   * Constant VALIDATION_INVALID_SCOPE
   *
   * @var string
   */
  public const string VALIDATION_INVALID_SCOPE = 'invalid_scope';
  //#endregion

  //#region Methods
  /**
   * Method validateAccessToken
   *
   * Validates an access token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AccessToken|null $token The access token.
   * @param list<string> $requiredScopes Required scopes (optional).
   *
   * @return TokenValidationResult The validation result.
   */
  public function validateAccessToken(
    ?AccessToken $token,
    array $requiredScopes = [],
  ): TokenValidationResult {
    if ($token === null) {
      return TokenValidationResult::failed(self::VALIDATION_NOT_FOUND, 'Token not found');
    }

    if ($token->isRevoked()) {
      return TokenValidationResult::failed(self::VALIDATION_REVOKED, 'Token has been revoked');
    }

    if ($token->isExpired()) {
      return TokenValidationResult::failed(self::VALIDATION_EXPIRED, 'Token has expired');
    }

    if (!empty($requiredScopes)) {
      $tokenScopes = $token->scopes()->toArray();
      foreach ($requiredScopes as $requiredScope) {
        if (!in_array($requiredScope, $tokenScopes, true)) {
          return TokenValidationResult::failed(
            self::VALIDATION_INVALID_SCOPE,
            sprintf('Missing required scope: %s', $requiredScope)
          );
        }
      }
    }

    return TokenValidationResult::success(
      tokenId: $token->identifier(),
      userId: $token->userIdentifier(),
      clientId: (string) $token->clientIdentifier(),
      scopes: $token->scopes()->toArray(),
      expiresAt: $token->expiry()->getTimestamp(),
    );
  }

  /**
   * Method validateRefreshToken
   *
   * Validates a refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RefreshToken|null $token The refresh token.
   *
   * @return TokenValidationResult The validation result.
   */
  public function validateRefreshToken(?RefreshToken $token): TokenValidationResult
  {
    if ($token === null) {
      return TokenValidationResult::failed(self::VALIDATION_NOT_FOUND, 'Refresh token not found');
    }

    if ($token->isRevoked()) {
      return TokenValidationResult::failed(self::VALIDATION_REVOKED, 'Refresh token has been revoked');
    }

    if ($token->isExpired()) {
      return TokenValidationResult::failed(self::VALIDATION_EXPIRED, 'Refresh token has expired');
    }

    return TokenValidationResult::success(
      tokenId: $token->identifier(),
      expiresAt: $token->expiryDateTime()->getTimestamp(),
    );
  }

  /**
   * Method canRefresh
   *
   * Checks if a refresh token can be used to issue new tokens.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RefreshToken|null $token The refresh token.
   *
   * @return bool True if can refresh.
   */
  public function canRefresh(?RefreshToken $token): bool
  {
    if ($token === null) {
      return false;
    }

    return !$token->isRevoked() && !$token->isExpired();
  }
  //#endregion
}
