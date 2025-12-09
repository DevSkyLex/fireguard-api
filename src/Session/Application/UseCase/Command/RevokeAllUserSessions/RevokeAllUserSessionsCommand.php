<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeAllUserSessions;

/**
 * Command RevokeAllUserSessionsCommand
 * @final
 *
 * Command to revoke all sessions for a user.
 *
 * @category Command
 * @package Session\Application\UseCase\Command\RevokeAllUserSessions
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsCommand
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $reason The reason for revocation.
   */
  public function __construct(
    public string $userId,
    public string $reason = 'User requested logout from all devices',
  ) {
  }
  //#endregion
}
