<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeOtherUserSessions;

use Session\Application\Port\Outbound\SessionRepositoryPort;

/**
 * Handler RevokeOtherUserSessionsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeOtherUserSessionsHandler implements \Shared\Application\Message\CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository the session repository
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RevokeOtherUserSessionsCommand. Revokes every active session
   * of the user except the one identified by currentSessionId, so the caller
   * stays signed in on this device while every other device is signed out.
   * Idempotent: a second call once there is nothing left to revoke affects
   * zero sessions.
   *
   * @since 1.0.0
   *
   * @param RevokeOtherUserSessionsCommand $command the command to handle
   *
   * @return RevokeOtherUserSessionsResult the result
   */
  public function __invoke(RevokeOtherUserSessionsCommand $command): RevokeOtherUserSessionsResult
  {
    $revokedCount = $this->sessionRepository->revokeAllForUserExcept(
      userId: $command->userId,
      exceptSessionId: $command->currentSessionId,
    );

    return new RevokeOtherUserSessionsResult(
      revokedCount: $revokedCount,
    );
  }
  // #endregion
}
