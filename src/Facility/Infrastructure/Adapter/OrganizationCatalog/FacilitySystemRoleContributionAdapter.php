<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

/**
 * Class FacilitySystemRoleContributionAdapter
 *
 * Publishes canonical grants owned by this module.
 *
 * @category Adapter
 */
final readonly class FacilitySystemRoleContributionAdapter implements OrganizationSystemRoleContributionPort
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
      new OrganizationSystemRoleDefinition('facility.member', 'member', ['organization.facilities.read'], 20),
    ];
  }
  // #endregion
}
