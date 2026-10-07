<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Intervention;

use Customer\Application\Port\Inbound\CustomerLookupPort;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\FacilityCustomerUnavailable;
use Intervention\Application\Port\Outbound\InterventionSiteCustomerSnapshotPort;

/**
 * Class InterventionSiteCustomerSnapshotAdapter
 *
 * Captures published site and retained customer identity without crossing persistence ownership.
 *
 * @category Adapter
 */
final readonly class InterventionSiteCustomerSnapshotAdapter implements InterventionSiteCustomerSnapshotPort
{
  public function __construct(private EntityManagerInterface $entityManager, private CustomerLookupPort $customers)
  {
  }

  /**
   * @return array{site:?array{id:string,name:string},customer:?array{id:string,name:string,contacts:list<array<string,mixed>>}}
   */
  public function snapshot(string $organizationId, ?string $siteId): array
  {
    if (null === $siteId) {
      return ['site' => null, 'customer' => null];
    }
    /** @var array{id:string,name:string}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative("SELECT id, name FROM facilities WHERE id = :id AND organization_id = :organization AND record_status = 'published'", ['id' => $siteId, 'organization' => $organizationId]);
    if (false === $row) {
      throw new FacilityCustomerUnavailable();
    }
    /** @var array{customer_id:?string}|false $root */
    $root = $this->entityManager->getConnection()->fetchAssociative(<<<'SQL'
      WITH RECURSIVE ancestry AS (
        SELECT id, type, parent_facility_id, customer_id, ARRAY[id::text] AS path
        FROM facilities
        WHERE id = :id AND organization_id = :organization AND record_status = 'published'
        UNION ALL
        SELECT p.id, p.type, p.parent_facility_id, p.customer_id, a.path || p.id::text
        FROM facilities p JOIN ancestry a ON p.id = a.parent_facility_id
        WHERE p.organization_id = :organization AND p.record_status = 'published'
          AND NOT p.id::text = ANY(a.path) AND cardinality(a.path) < 64
      )
      SELECT customer_id FROM ancestry WHERE type = 'site' AND parent_facility_id IS NULL
      SQL, ['id' => $siteId, 'organization' => $organizationId]);
    $customerId = false === $root ? null : $root['customer_id'];
    $customer = null === $customerId ? null : $this->customers->find($customerId, $organizationId);
    if (null !== $customerId && null === $customer) {
      throw new FacilityCustomerUnavailable();
    }

    return ['site' => ['id' => $row['id'], 'name' => $row['name']], 'customer' => null === $customer ? null : ['id' => $customer->id, 'name' => $customer->name, 'contacts' => $customer->contacts]];
  }
}
