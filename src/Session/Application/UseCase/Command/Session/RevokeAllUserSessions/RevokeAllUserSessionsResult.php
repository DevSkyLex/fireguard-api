<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeAllUserSessions;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeAllUserSessionsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $revokedCount the number of sessions revoked
   */
  public function __construct(
    public int $revokedCount,
  ) {
  }
  // #endregion
}
