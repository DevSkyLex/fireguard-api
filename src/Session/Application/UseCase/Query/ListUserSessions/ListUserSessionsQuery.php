<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\ListUserSessions;

/**
 * Query ListUserSessionsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserSessionsQuery
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param bool $activeOnly whether to return only active sessions
   */
  public function __construct(
    public readonly string $userId,
    public readonly bool $activeOnly = true,
  ) {
  }
  // #endregion
}
