<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Team\AddTeamMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddTeamMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTeamMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddTeamMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $teamId the team identifier
   * @param string $memberId the organization member identifier to add
   * @param ?string $role the free-form membership label, when provided
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    public string $memberId,
    public ?string $role = null,
  ) {
  }
  // #endregion
}
