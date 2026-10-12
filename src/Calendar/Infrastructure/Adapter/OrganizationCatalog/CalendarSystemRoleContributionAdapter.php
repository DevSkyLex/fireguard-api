<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

/**
 * Class CalendarSystemRoleContributionAdapter
 *
 * Publishes canonical grants owned by this module.
 *
 * @category Adapter
 */
final readonly class CalendarSystemRoleContributionAdapter implements OrganizationSystemRoleContributionPort
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
      new OrganizationSystemRoleDefinition('calendar.member', 'member', ['organization.events.read'], 120),
    ];
  }
  // #endregion
}
