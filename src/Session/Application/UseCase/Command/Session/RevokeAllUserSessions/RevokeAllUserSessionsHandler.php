<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeAllUserSessions;

use Session\Application\Port\Outbound\SessionRepositoryPort;

/**
 * Handler RevokeAllUserSessionsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsHandler implements \Shared\Application\Message\CommandHandler
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
   * Handles the RevokeAllUserSessionsCommand.
   *
   * @since 1.0.0
   *
   * @param RevokeAllUserSessionsCommand $command the command to handle
   *
   * @return RevokeAllUserSessionsResult the result
   */
  public function __invoke(RevokeAllUserSessionsCommand $command): RevokeAllUserSessionsResult
  {
    $revokedCount = $this->sessionRepository->revokeAllForUser(
      userId: $command->userId,
    );

    return new RevokeAllUserSessionsResult(
      revokedCount: $revokedCount,
    );
  }
  // #endregion
}
