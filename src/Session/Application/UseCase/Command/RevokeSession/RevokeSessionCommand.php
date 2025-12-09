<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeSession;

/**
 * Command RevokeSessionCommand
 * @final
 *
 * Command to revoke a session.
 *
 * @category Command
 * @package Session\Application\UseCase\Command\RevokeSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionCommand
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $sessionId The session ID to revoke.
   * @param string $reason The reason for revocation.
   */
  public function __construct(
    public string $sessionId,
    public string $reason = 'User logout',
  ) {
  }
  //#endregion
}
