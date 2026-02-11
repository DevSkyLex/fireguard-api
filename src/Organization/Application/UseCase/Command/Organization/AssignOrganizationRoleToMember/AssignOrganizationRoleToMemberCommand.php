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
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $roleId,
  ) {
  }
  // #endregion
}
