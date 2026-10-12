<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, OrganizationQuotaContributionPort};

/**
 * Class EquipmentQuotaContributionAdapter
 *
 * Publishes the owner's quota definition and scoped active usage.
 *
 * @category Adapter
 */
final readonly class EquipmentQuotaContributionAdapter implements OrganizationQuotaContributionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   */
  public function __construct(private EquipmentStatisticsPort $statistics)
  {
  }

  // #endregion

  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationQuotaDefinition>
   */
  public static function definitions(): array
  {
    return [new OrganizationQuotaDefinition('equipment', 'Equipment', 'equipment items', 'Maximum number of equipment items', 30)];
  }

  /**
   * Method usage
   *
   * @access public
   *
   * @return int current active resource count
   */
  public function usage(string $organizationId, string $resourceKey): int
  {
    return $this->statistics->countActiveEquipment($organizationId);
  }
  // #endregion
}
