<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeAllUserSessions;

/**
 * Result RevokeAllUserSessionsResult
 * @final
 *
 * Result of revoking all user sessions.
 *
 * @category Result
 * @package Session\Application\UseCase\Command\RevokeAllUserSessions
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $revokedCount The number of sessions revoked.
   */
  public function __construct(
    public int $revokedCount,
  ) {
  }
  //#endregion
}
