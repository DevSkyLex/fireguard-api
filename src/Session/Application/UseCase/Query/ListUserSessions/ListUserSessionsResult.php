<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\ListUserSessions;

use Session\Application\UseCase\Query\GetSession\GetSessionResult;

/**
 * Result ListUserSessionsResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserSessionsResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUserSessionsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetSessionResult> $sessions   the sessions
   * @param int                    $totalCount the total count
   */
  public function __construct(
    public readonly array $sessions,
    public readonly int $totalCount,
  ) {
  }
  // #endregion
}
