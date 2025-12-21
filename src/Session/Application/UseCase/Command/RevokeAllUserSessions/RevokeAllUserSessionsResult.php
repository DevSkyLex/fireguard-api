<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\RevokeAllUserSessions;

/**
 * Result RevokeAllUserSessionsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeAllUserSessionsResult
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
