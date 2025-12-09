<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\CreateSession;

/**
 * Result CreateSessionResult
 * @final
 *
 * Result of session creation.
 *
 * @category Result
 * @package Session\Application\UseCase\Command\CreateSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $sessionId The created session ID.
   */
  public function __construct(
    public string $sessionId,
  ) {
  }
  //#endregion
}
