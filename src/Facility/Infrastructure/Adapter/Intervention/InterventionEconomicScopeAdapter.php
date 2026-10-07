<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Publication\InterventionFactsScopeTooLarge;
use Intervention\Application\Port\Outbound\InterventionEconomicScopePort;

use function count;

/**
 * Class InterventionEconomicScopeAdapter
 *
 * Resolves live economic scopes from the owned published hierarchy while retaining archived location identities.
 *
 * @category Adapter
 */
final readonly class InterventionEconomicScopeAdapter implements InterventionEconomicScopePort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the explicit main connection, never a cross-module private persistence join.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager owning main manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method facilityIds
   *
   * Fences every recursive edge by organization and refuses scopes larger than 10000 locations.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param ?string $siteId optional root site
   * @param ?string $customerId optional internal client
   *
   * @return list<string> unique matching root and descendant identities
   */
  public function facilityIds(string $organizationId, ?string $siteId, ?string $customerId): array
  {
    if (null === $siteId && null === $customerId) {
      return [];
    }
    $rootFilters = '';
    $parameters = ['organization' => $organizationId];
    if (null !== $siteId) {
      $rootFilters .= ' AND id = :site';
      $parameters['site'] = $siteId;
    }
    if (null !== $customerId) {
      $rootFilters .= ' AND customer_id = :customer';
      $parameters['customer'] = $customerId;
    }
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn("WITH RECURSIVE scope AS (
      SELECT id, ARRAY[id::text] AS path FROM facilities
      WHERE organization_id = :organization AND record_status = 'published' AND type = 'site' AND parent_facility_id IS NULL" . $rootFilters . "
      UNION ALL
      SELECT f.id, s.path || f.id::text FROM facilities f JOIN scope s ON f.parent_facility_id = s.id
      WHERE f.organization_id = :organization AND f.record_status = 'published'
        AND NOT f.id::text = ANY(s.path) AND cardinality(s.path) < 64
      ) SELECT id FROM scope ORDER BY id LIMIT 10001", $parameters);
    if (count($ids) > 10000) {
      throw new InterventionFactsScopeTooLarge('The facility scope exceeds 10000 locations; narrow the requested scope.');
    }

    return $ids;
  }
  // #endregion
}
