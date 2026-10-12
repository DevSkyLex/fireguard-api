<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

/**
 * Class EquipmentSystemRoleContributionAdapter
 *
 * Publishes canonical grants owned by this module.
 *
 * @category Adapter
 */
final readonly class EquipmentSystemRoleContributionAdapter implements OrganizationSystemRoleContributionPort
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
      new OrganizationSystemRoleDefinition('equipment.member', 'member', ['organization.equipment.read'], 40),
    ];
  }
  // #endregion
}
