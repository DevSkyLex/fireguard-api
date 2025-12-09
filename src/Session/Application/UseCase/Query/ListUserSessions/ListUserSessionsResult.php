<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\ListUserSessions;

use Session\Application\UseCase\Query\GetSession\GetSessionResult;

/**
 * Result ListUserSessionsResult
 * @final
 *
 * Result of listing user sessions.
 *
 * @category Result
 * @package Session\Application\UseCase\Query\ListUserSessions
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserSessionsResult
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * ListUserSessionsResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param list<GetSessionResult> $sessions The sessions.
   * @param int $totalCount The total count.
   */
  public function __construct(
    public readonly array $sessions,
    public readonly int $totalCount,
  ) {}
  //#endregion
}
