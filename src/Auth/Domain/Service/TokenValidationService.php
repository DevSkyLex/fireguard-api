<?php

declare(strict_types=1);

namespace Auth\Domain\Service;

use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\Model\Token\RefreshToken;

use function in_array;
use function sprintf;

/**
 * Service TokenValidationService.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenValidationService
{
  // #region Constants
  /**
   * Constant VALIDATION_OK.
   *
   * @var string
   */
  public const string VALIDATION_OK = 'ok';

  /**
   * Constant VALIDATION_EXPIRED.
   *
   * @var string
   */
  public const string VALIDATION_EXPIRED = 'expired';

  /**
   * Constant VALIDATION_REVOKED.
   *
   * @var string
   */
  public const string VALIDATION_REVOKED = 'revoked';

  /**
   * Constant VALIDATION_NOT_FOUND.
   *
   * @var string
   */
  public const string VALIDATION_NOT_FOUND = 'not_found';

  /**
   * Constant VALIDATION_INVALID_SCOPE.
   *
   * @var string
   */
  public const string VALIDATION_INVALID_SCOPE = 'invalid_scope';
  // #endregion

  // #region Methods
  /**
   * Method validateAccessToken.
   *
   * Validates an access token.
   *
   * @since 1.0.0
   *
   * @param AccessToken|null $token the access token
   * @param list<string> $requiredScopes required scopes (optional)
   *
   * @return TokenValidationResult the validation result
   */
  public function validateAccessToken(
    ?AccessToken $token,
    array $requiredScopes = [],
  ): TokenValidationResult {
    if (null === $token) {
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
            sprintf('Missing required scope: %s', $requiredScope),
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
   * Method validateRefreshToken.
   *
   * Validates a refresh token.
   *
   * @since 1.0.0
   *
   * @param RefreshToken|null $token the refresh token
   *
   * @return TokenValidationResult the validation result
   */
  public function validateRefreshToken(?RefreshToken $token): TokenValidationResult
  {
    if (null === $token) {
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
   * Method canRefresh.
   *
   * Checks if a refresh token can be used to issue new tokens.
   *
   * @since 1.0.0
   *
   * @param RefreshToken|null $token the refresh token
   *
   * @return bool true if can refresh
   */
  public function canRefresh(?RefreshToken $token): bool
  {
    if (null === $token) {
      return false;
    }

    return !$token->isRevoked() && !$token->isExpired();
  }
  // #endregion
}
