<?php

declare(strict_types=1);

namespace OAuth\Application\Port\Outbound\Token;

/**
 * Interface GrantLifecyclePort
 *
 * Serializes user grant validation and issuance with credential-driven revocation.
 *
 * @category Outbound Port
 */
interface GrantLifecyclePort
{
  // #region Methods
  /**
   * Method transactional
   *
   * @template T
   *
   * @param callable():T $operation the complete auth-owned grant operation
   *
   * @return T the committed result
   */
  public function transactional(callable $operation): mixed;

  /**
   * Method lockUser
   *
   * @param string $userId the stored grant owner
   *
   * @return void
   */
  public function lockUser(string $userId): void;

  /**
   * Method isAuthCodeRevoked
   *
   * Locks the stored owner before deciding using current persisted state.
   *
   * @param string $identifier the code identifier
   *
   * @return bool whether the code is unavailable
   */
  public function isAuthCodeRevoked(string $identifier): bool;

  /**
   * Method isRefreshTokenRevoked
   *
   * Locks the original access token's owner before deciding using current persisted state.
   *
   * @param string $identifier the refresh token identifier
   *
   * @return bool whether the refresh grant is unavailable
   */
  public function isRefreshTokenRevoked(string $identifier): bool;

  /**
   * Method isAccessTokenUsable
   *
   * @param string $identifier the verified bearer identifier
   * @param string $userId the authenticated principal identifier
   *
   * @return bool whether the delegated principal remains valid
   */
  public function isAccessTokenUsable(string $identifier, string $userId): bool;
  // #endregion
}
