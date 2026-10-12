<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\OrganizationCatalog;

use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Port\Outbound\{InspectionStatisticsPort, OrganizationQuotaContributionPort};

/**
 * Class InspectionQuotaContributionAdapter
 *
 * Publishes the owner's quota definition and scoped active usage.
 *
 * @category Adapter
 */
final readonly class InspectionQuotaContributionAdapter implements OrganizationQuotaContributionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   */
  public function __construct(private InspectionStatisticsPort $statistics)
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
    return [new OrganizationQuotaDefinition('inspections', 'Inspections', 'inspections', 'Maximum number of inspections', 40)];
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
    return $this->statistics->countActiveInspections($organizationId);
  }
  // #endregion
}
