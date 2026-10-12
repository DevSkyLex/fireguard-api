<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

/**
 * Class InterventionSystemRoleContributionAdapter
 *
 * Publishes canonical grants owned by this module.
 *
 * @category Adapter
 */
final readonly class InterventionSystemRoleContributionAdapter implements OrganizationSystemRoleContributionPort
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
      new OrganizationSystemRoleDefinition('intervention.member', 'member', ['organization.interventions.read'], 60),
    ];
  }
  // #endregion
}
