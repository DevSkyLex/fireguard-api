<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\GetSession;

/**
 * Query GetSessionQuery
 * @final
 *
 * Query to get a session by ID.
 *
 * @category Query
 * @package Session\Application\UseCase\Query\GetSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionQuery
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $sessionId The session ID.
   */
  public function __construct(
    public string $sessionId,
  ) {
  }
  //#endregion
}
