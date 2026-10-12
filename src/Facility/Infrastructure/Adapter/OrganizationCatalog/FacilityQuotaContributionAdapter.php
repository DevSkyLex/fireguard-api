<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Port\Outbound\{FacilityStatisticsPort, OrganizationQuotaContributionPort};

/**
 * Class FacilityQuotaContributionAdapter
 *
 * Publishes the owner's quota definition and scoped active usage.
 *
 * @category Adapter
 */
final readonly class FacilityQuotaContributionAdapter implements OrganizationQuotaContributionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   */
  public function __construct(private FacilityStatisticsPort $statistics)
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
    return [new OrganizationQuotaDefinition('facilities', 'Facilities', 'facilities', 'Maximum number of facilities', 20)];
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
    return $this->statistics->countActiveFacilities($organizationId);
  }
  // #endregion
}
