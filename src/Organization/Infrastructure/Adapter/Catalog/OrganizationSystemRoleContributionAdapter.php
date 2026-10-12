<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Catalog;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

/**
 * Class OrganizationSystemRoleContributionAdapter
 *
 * Publishes canonical grants owned by this module.
 *
 * @category Adapter
 */
final readonly class OrganizationSystemRoleContributionAdapter implements OrganizationSystemRoleContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationSystemRoleDefinition>
   */
  public static function definitions(): array
  {
    return [
      new OrganizationSystemRoleDefinition('organization.admin', 'admin', ['organization.*'], 0),
      new OrganizationSystemRoleDefinition('organization.member', 'member', ['organization.read', 'organization.dashboard.read', 'organization.members.read', 'organization.roles.read'], 10),
      new OrganizationSystemRoleDefinition('organization.member.teams', 'member', ['organization.teams.read'], 80),
    ];
  }
  // #endregion
}
