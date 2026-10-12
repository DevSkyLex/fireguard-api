<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Outbound\OrganizationSettingsPolicyContributionPort;

/**
 * Class EquipmentSettingsPolicyContributionAdapter
 *
 * Publishes the owner defaults and structural settings constraints.
 *
 * @category Adapter
 */
final readonly class EquipmentSettingsPolicyContributionAdapter implements OrganizationSettingsPolicyContributionPort
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
      new OrganizationSettingsPolicyDefinition('compliance.inspection_periodicity_defaults', OrganizationSettingsValueType::TEXT_MAP, ['fire_extinguisher' => 'P1Y', 'smoke_detector' => 'P1Y', 'heat_detector' => 'P1Y', 'sprinkler' => 'P1Y', 'fire_alarm_panel' => 'P6M', 'hydrant' => 'P1Y', 'fire_door' => 'P6M', 'emergency_lighting' => 'P6M', 'access_control' => 'P1Y', 'camera' => 'P1Y', 'gas_detector' => 'P6M']),
    ];
  }
  // #endregion
}
