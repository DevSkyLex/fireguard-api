<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\SetOrganizationMemberRoles;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetOrganizationMemberRolesCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetOrganizationMemberRolesCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * SetOrganizationMemberRolesCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $actingUserId the identifier of the user performing the role assignment
   * @param string $memberId the member identifier whose roles are being replaced
   * @param list<string> $roleIds the complete set of role identifiers the member must hold afterward
   */
  public function __construct(
    public string $organizationId,
    public string $actingUserId,
    public string $memberId,
    public array $roleIds,
  ) {
  }
  // #endregion
}
