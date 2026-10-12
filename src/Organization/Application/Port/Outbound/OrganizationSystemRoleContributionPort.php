<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Application\Contract\Authorization\OrganizationSystemRoleDefinition;

/**
 * Interface OrganizationSystemRoleContributionPort
 *
 * Publishes pure system-role grants; definitions must not instantiate services or read persistence.
 *
 * @category Port
 */
interface OrganizationSystemRoleContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationSystemRoleDefinition> compile-safe owned definitions
   */
  public static function definitions(): array;
  // #endregion
}
