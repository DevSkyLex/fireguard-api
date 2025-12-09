<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeAllUserSessions;

use Session\Application\Port\Outbound\SessionRepositoryPort;

/**
 * Handler RevokeAllUserSessionsHandler
 * @final
 *
 * Handles revoking all sessions for a user.
 *
 * @category Handler
 * @package Session\Application\UseCase\Command\RevokeAllUserSessions
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param SessionRepositoryPort $sessionRepository The session repository.
   */
  public function __construct(
    private SessionRepositoryPort $sessionRepository,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RevokeAllUserSessionsCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RevokeAllUserSessionsCommand $command The command to handle.
   *
   * @return RevokeAllUserSessionsResult The result.
   */
  public function __invoke(RevokeAllUserSessionsCommand $command): RevokeAllUserSessionsResult
  {
    $revokedCount = $this->sessionRepository->revokeAllForUser(
      userId: $command->userId
    );

    return new RevokeAllUserSessionsResult(
      revokedCount: $revokedCount,
    );
  }
  //#endregion
}
