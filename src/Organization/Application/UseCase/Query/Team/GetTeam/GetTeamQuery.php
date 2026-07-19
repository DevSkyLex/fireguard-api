<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\GetTeam;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetTeamQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTeamQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetTeamQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
  ) {
  }
  // #endregion
}
