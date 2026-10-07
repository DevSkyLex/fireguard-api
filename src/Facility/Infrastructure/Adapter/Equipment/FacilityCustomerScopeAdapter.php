<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\FacilityCustomerScopePort;

/**
 * Resolves the published customer portfolio within its owning organization.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityCustomerScopeAdapter implements FacilityCustomerScopePort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * @since 1.0.0
   *
   * @return list<string>
   */
  public function findPublishedIdsForCustomer(string $organizationId, string $customerId): array
  {
    $sql = <<<'SQL'
      WITH RECURSIVE portfolio AS (
        SELECT id FROM facilities
        WHERE organization_id = :organizationId AND record_status = 'published'
          AND parent_facility_id IS NULL AND type = 'site' AND customer_id = :customerId
        UNION
        SELECT child.id FROM facilities child INNER JOIN portfolio ON child.parent_facility_id = portfolio.id
        WHERE child.organization_id = :organizationId AND child.record_status = 'published'
      )
      SELECT id FROM portfolio ORDER BY id
      SQL;

    /** @var list<string> */
    return $this->entityManager->getConnection()->executeQuery($sql, ['organizationId' => $organizationId, 'customerId' => $customerId])->fetchFirstColumn();
  }
}
