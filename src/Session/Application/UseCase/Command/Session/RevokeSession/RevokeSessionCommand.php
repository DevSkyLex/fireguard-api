<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeSession;

use Shared\Application\Message\CommandMessage;

/**
 * Command RevokeSessionCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sessionId the session ID to revoke
   * @param string $reason the reason for revocation
   */
  public function __construct(
    public string $sessionId,
    public string $reason = 'User logout',
  ) {
  }
  // #endregion
}
