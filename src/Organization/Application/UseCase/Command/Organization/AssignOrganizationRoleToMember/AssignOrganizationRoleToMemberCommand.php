<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AssignOrganizationRoleToMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AssignOrganizationRoleToMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignOrganizationRoleToMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AssignOrganizationRoleToMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $memberId the member ID
   * @param string $roleId the role ID
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $roleId,
  ) {
  }
  // #endregion
}
