<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Outbound\OrganizationSettingsPolicyContributionPort;

/**
 * Class AssistantSettingsPolicyContributionAdapter
 *
 * Publishes the owner defaults and structural settings constraints.
 *
 * @category Adapter
 */
final readonly class AssistantSettingsPolicyContributionAdapter implements OrganizationSettingsPolicyContributionPort
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
      new OrganizationSettingsPolicyDefinition('assistant.enabled', OrganizationSettingsValueType::BOOLEAN, false),
      new OrganizationSettingsPolicyDefinition('assistant.model', OrganizationSettingsValueType::TEXT, null, nullResets: true),
      new OrganizationSettingsPolicyDefinition('assistant.temperature', OrganizationSettingsValueType::NUMBER, 0.2, 0.0, 2.0),
      new OrganizationSettingsPolicyDefinition('assistant.include_business_context', OrganizationSettingsValueType::BOOLEAN, true),
    ];
  }
  // #endregion
}
