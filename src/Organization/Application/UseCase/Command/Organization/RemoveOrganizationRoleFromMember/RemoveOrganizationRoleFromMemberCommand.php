<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationRoleFromMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RemoveOrganizationRoleFromMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationRoleFromMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RemoveOrganizationRoleFromMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $memberId the member identifier
   * @param string $roleId the role identifier to remove
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $roleId,
  ) {
  }
  // #endregion
}
