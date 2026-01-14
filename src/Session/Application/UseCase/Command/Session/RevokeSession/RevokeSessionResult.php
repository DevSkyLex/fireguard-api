<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeSession;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeSessionResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $revoked whether the session was revoked
   * @param string $sessionId the revoked session ID
   */
  public function __construct(
    public bool $revoked,
    public string $sessionId,
  ) {
  }
  // #endregion
}
