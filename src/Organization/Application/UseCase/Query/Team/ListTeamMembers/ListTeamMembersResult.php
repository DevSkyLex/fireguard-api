<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Team\ListTeamMembers;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListTeamMembersResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTeamMembersResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamMembersResult class.
   *
   * @since 1.0.0
   *
   * @param list<TeamMembershipResult> $memberships the team membership rows
   */
  public function __construct(
    public array $memberships,
  ) {
  }
  // #endregion
}
