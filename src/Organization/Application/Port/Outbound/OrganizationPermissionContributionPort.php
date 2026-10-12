<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Application\Contract\Authorization\OrganizationPermissionDefinition;

/**
 * Interface OrganizationPermissionContributionPort
 *
 * Publishes pure owner-defined permission metadata for container compilation and runtime catalogs.
 *
 * @category Port
 */
interface OrganizationPermissionContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationPermissionDefinition>
   */
  public static function definitions(): array;
  // #endregion
}
