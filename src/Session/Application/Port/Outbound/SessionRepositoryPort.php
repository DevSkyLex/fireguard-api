<?php

declare(strict_types=1);

namespace Session\Application\Port\Outbound;

use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;

/**
 * Interface SessionRepositoryPort.
 *
 * Port for Session persistence.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SessionRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Saves a session.
   *
   * @since 1.0.0
   *
   * @param Session $session the session to save
   */
  public function save(Session $session): void;

  /**
   * Method findById.
   *
   * Finds a session by ID.
   *
   * @since 1.0.0
   *
   * @param SessionId $id the session ID
   *
   * @return Session|null the session or null if not found
   */
  public function findById(SessionId $id): ?Session;

  /**
   * Method findByUserId.
   *
   * Finds all sessions for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return list<Session> the user's sessions
   */
  public function findByUserId(string $userId): array;

  /**
   * Method findActiveByUserId.
   *
   * Finds all active (non-revoked) sessions for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return list<Session> the user's active sessions
   */
  public function findActiveByUserId(string $userId): array;

  /**
   * Method findByAccessTokenId.
   *
   * Finds a session by access token ID.
   *
   * @since 1.0.0
   *
   * @param string $accessTokenId the access token ID
   *
   * @return Session|null the session or null if not found
   */
  public function findByAccessTokenId(string $accessTokenId): ?Session;

  /**
   * Method findByRefreshTokenId.
   *
   * Finds a session by refresh token ID.
   *
   * @since 1.0.0
   *
   * @param string $refreshTokenId the refresh token ID
   *
   * @return Session|null the session or null if not found
   */
  public function findByRefreshTokenId(string $refreshTokenId): ?Session;

  /**
   * Method revokeAllForUser.
   *
   * Revokes all sessions for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return int the number of sessions revoked
   */
  public function revokeAllForUser(string $userId): int;

  /**
   * Method revokeAllForUserExcept.
   *
   * Revokes all active sessions for a user except the one identified by
   * exceptSessionId, so the caller stays signed in on that session while
   * every other device is signed out. Idempotent: revoking a second time
   * once nothing else is active returns zero.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $exceptSessionId the session ID to keep active
   *
   * @return int the number of sessions revoked
   */
  public function revokeAllForUserExcept(string $userId, string $exceptSessionId): int;

  /**
   * Method delete.
   *
   * Deletes a session.
   *
   * @since 1.0.0
   *
   * @param SessionId $id the session ID
   */
  public function delete(SessionId $id): void;
  // #endregion
}
