<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\RevokeSessionByToken;

use Shared\Application\Message\ResultMessage;

/**
 * Result RevokeSessionByTokenResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RevokeSessionByTokenResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $revoked whether the session was revoked
   * @param string|null $sessionId the revoked session ID
   */
  public function __construct(
    public bool $revoked,
    public ?string $sessionId,
  ) {
  }
  // #endregion
}
