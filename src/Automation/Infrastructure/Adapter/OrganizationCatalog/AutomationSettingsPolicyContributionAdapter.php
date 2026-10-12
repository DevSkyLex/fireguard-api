<?php

declare(strict_types=1);

namespace Automation\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Outbound\OrganizationSettingsPolicyContributionPort;

/**
 * Class AutomationSettingsPolicyContributionAdapter
 *
 * Publishes the owner defaults and structural settings constraints.
 *
 * @category Adapter
 */
final readonly class AutomationSettingsPolicyContributionAdapter implements OrganizationSettingsPolicyContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationSettingsPolicyDefinition>
   */
  public static function definitions(): array
  {
    return [
      new OrganizationSettingsPolicyDefinition('automation.auto_create_intervention_on_critical_nc', OrganizationSettingsValueType::BOOLEAN, false),
    ];
  }
  // #endregion
}
