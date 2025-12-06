<?php

declare(strict_types=1);

namespace Session\Application\Port\Outbound;

use Session\Domain\Model\Session;
use Session\Domain\ValueObject\SessionId;

/**
 * Interface SessionRepositoryPort
 *
 * Port for Session persistence.
 *
 * @category Port
 * @package Session\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface SessionRepositoryPort
{
  //#region Methods
  /**
   * Method save
   *
   * Saves a session.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Session $session The session to save.
   *
   * @return void
   */
  public function save(Session $session): void;

  /**
   * Method findById
   *
   * Finds a session by ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param SessionId $id The session ID.
   *
   * @return Session|null The session or null if not found.
   */
  public function findById(SessionId $id): ?Session;

  /**
   * Method findByUserId
   *
   * Finds all sessions for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   *
   * @return list<Session> The user's sessions.
   */
  public function findByUserId(string $userId): array;

  /**
   * Method findActiveByUserId
   *
   * Finds all active (non-revoked) sessions for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   *
   * @return list<Session> The user's active sessions.
   */
  public function findActiveByUserId(string $userId): array;

  /**
   * Method revokeAllForUser
   *
   * Revokes all sessions for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   *
   * @return int The number of sessions revoked.
   */
  public function revokeAllForUser(string $userId): int;

  /**
   * Method delete
   *
   * Deletes a session.
   *
   * @access public
   * @since 1.0.0
   *
   * @param SessionId $id The session ID.
   *
   * @return void
   */
  public function delete(SessionId $id): void;
  //#endregion
}
