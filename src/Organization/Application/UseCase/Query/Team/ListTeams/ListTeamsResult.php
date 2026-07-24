<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\ListTeams;

use Organization\Application\UseCase\Query\Team\GetTeam\GetTeamResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListTeamsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTeamsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetTeamResult> $teams the organization teams
   */
  public function __construct(
    public array $teams,
  ) {
  }
  // #endregion
}
