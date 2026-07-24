<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeOtherUserSessions;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeOtherUserSessionsCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeOtherUserSessionsCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $currentSessionId the session ID to keep active (the caller's own session)
   * @param string $reason the reason for revocation
   */
  public function __construct(
    public string $userId,
    public string $currentSessionId,
    public string $reason = 'User requested logout from other devices',
  ) {
  }
  // #endregion
}
