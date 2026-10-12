<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;
use Organization\Application\Port\Outbound\OrganizationSystemRoleContributionPort;

/**
 * Class MessagingSystemRoleContributionAdapter
 *
 * Publishes canonical grants owned by this module.
 *
 * @category Adapter
 */
final readonly class MessagingSystemRoleContributionAdapter implements OrganizationSystemRoleContributionPort
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
      new OrganizationSystemRoleDefinition('messaging.member', 'member', ['organization.messaging.read'], 90),
    ];
  }
  // #endregion
}
