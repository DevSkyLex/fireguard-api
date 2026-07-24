<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\RemoveTeamMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RemoveTeamMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTeamMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RemoveTeamMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   * @param string $memberId the organization member identifier to remove
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    public string $memberId,
  ) {
  }
  // #endregion
}
