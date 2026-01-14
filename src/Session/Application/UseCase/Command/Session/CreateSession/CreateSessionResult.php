<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\CreateSession;

use Shared\Application\Message\ResultMessage;

/**
 * Result CreateSessionResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sessionId the created session ID
   */
  public function __construct(
    public string $sessionId,
  ) {
  }
  // #endregion
}
