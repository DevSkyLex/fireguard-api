<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\RemoveOrganizationRoleFromMember;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveOrganizationRoleFromMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveOrganizationRoleFromMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RemoveOrganizationRoleFromMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the member identifier
   * @param string $organizationId the organization identifier
   * @param string $roleId the unassigned role identifier
   */
  public function __construct(
    public string $memberId,
    public string $organizationId,
    public string $roleId,
  ) {
  }
  // #endregion
}
