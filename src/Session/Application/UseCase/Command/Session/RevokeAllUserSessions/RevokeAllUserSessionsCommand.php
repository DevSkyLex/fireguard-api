<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeAllUserSessions;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeAllUserSessionsCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $reason the reason for revocation
   */
  public function __construct(
    public string $userId,
    public string $reason = 'User requested logout from all devices',
  ) {
  }
  // #endregion
}
