<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Application\Contract\Settings\OrganizationSettingsPolicyDefinition;

/**
 * Interface OrganizationSettingsPolicyContributionPort
 *
 * Publishes pure typed field definitions without dependencies or side effects during compilation.
 *
 * @category Port
 */
interface OrganizationSettingsPolicyContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationSettingsPolicyDefinition> owned field definitions
   */
  public static function definitions(): array;
  // #endregion
}
