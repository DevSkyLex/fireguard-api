<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Outbound\OrganizationSettingsPolicyContributionPort;

/**
 * Class InspectionSettingsPolicyContributionAdapter
 *
 * Publishes the owner defaults and structural settings constraints.
 *
 * @category Adapter
 */
final readonly class InspectionSettingsPolicyContributionAdapter implements OrganizationSettingsPolicyContributionPort
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
      new OrganizationSettingsPolicyDefinition('compliance.non_conformity_sla_days', OrganizationSettingsValueType::INTEGER_MAP, ['low' => 90, 'medium' => 30, 'high' => 7, 'critical' => 1], 1, 365, ['low', 'medium', 'high', 'critical']),
    ];
  }
  // #endregion
}
