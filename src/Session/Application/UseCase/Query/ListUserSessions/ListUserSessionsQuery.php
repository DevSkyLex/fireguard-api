<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\ListUserSessions;

/**
 * Query ListUserSessionsQuery
 * @final
 *
 * Query to list all sessions for a user.
 *
 * @category Query
 * @package Session\Application\UseCase\Query\ListUserSessions
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserSessionsQuery
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param bool $activeOnly Whether to return only active sessions.
   */
  public function __construct(
    public readonly string $userId,
    public readonly bool $activeOnly = true,
  ) {}
  //#endregion
}
