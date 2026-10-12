<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Settings\{OrganizationSettingsPolicyDefinition, OrganizationSettingsValueType};
use Organization\Application\Port\Outbound\OrganizationSettingsPolicyContributionPort;

/**
 * Class ApprovalSettingsPolicyContributionAdapter
 *
 * Publishes the owner defaults and structural settings constraints.
 *
 * @category Adapter
 */
final readonly class ApprovalSettingsPolicyContributionAdapter implements OrganizationSettingsPolicyContributionPort
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
      new OrganizationSettingsPolicyDefinition('approval.action_rules', OrganizationSettingsValueType::OBJECT_MAP, []),
      new OrganizationSettingsPolicyDefinition('approval.allow_self_approval', OrganizationSettingsValueType::BOOLEAN, false),
      new OrganizationSettingsPolicyDefinition('approval.approval_ttl_days', OrganizationSettingsValueType::INTEGER, 14, 1, 90),
    ];
  }
  // #endregion
}
