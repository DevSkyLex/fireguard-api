<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\RemoveTeamMember;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveTeamMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTeamMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveTeamMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $teamId the team identifier
   * @param string $memberId the removed organization member identifier
   */
  public function __construct(
    public string $teamId,
    public string $memberId,
  ) {
  }
  // #endregion
}
