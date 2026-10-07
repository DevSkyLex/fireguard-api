<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\ServiceRequest;

use Customer\Application\Port\Inbound\CustomerLookupPort;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use ServiceRequest\Application\Contract\Target\ServiceRequestSiteTarget;
use ServiceRequest\Application\Port\Outbound\ServiceRequestSiteTargetPort;

use function array_map;
use function count;
use function implode;

/** Class ServiceRequestSiteTargetAdapter. Resolves published ancestry and minimal customer identity on main. @category Adapter */
final readonly class ServiceRequestSiteTargetAdapter implements ServiceRequestSiteTargetPort
{
  public function __construct(private EntityManagerInterface $entityManager, private CustomerLookupPort $customers, private FacilityHierarchyPort $hierarchy)
  {
  }

  public function lock(string $organizationId): void
  {
    $this->hierarchy->lock($organizationId);
  }

  public function find(string $organizationId, ?string $siteId, ?string $facilityId): ?ServiceRequestSiteTarget
  {
    $targetId = $facilityId ?? $siteId;
    if (null === $targetId) {
      return null;
    }
    $connection = $this->entityManager->getConnection();
    /** @var list<array{id:string,name:string,type:string,parent_facility_id:?string,customer_id:?string,status:string}> $rows */
    $rows = $connection->fetchAllAssociative(<<<'SQL'
      WITH RECURSIVE ancestry AS (
        SELECT id, name, type, parent_facility_id, customer_id, status, ARRAY[id::text] AS path
        FROM facilities WHERE id = :target AND organization_id = :organization AND record_status = 'published'
        UNION ALL
        SELECT p.id, p.name, p.type, p.parent_facility_id, p.customer_id, p.status, a.path || p.id::text
        FROM facilities p JOIN ancestry a ON p.id = a.parent_facility_id
        WHERE p.organization_id = :organization AND p.record_status = 'published'
          AND NOT p.id::text = ANY(a.path) AND cardinality(a.path) < 64
      )
      SELECT id, name, type, parent_facility_id, customer_id, status FROM ancestry
      SQL, ['target' => $targetId, 'organization' => $organizationId]);
    if ([] === $rows) {
      return null;
    }
    $root = null;
    $archived = false;
    foreach ($rows as $row) {
      $archived = $archived || 'archived' === $row['status'];
      if ('site' === $row['type'] && null === $row['parent_facility_id']) {
        $root = $row;
      }
    }
    if (null === $root || (null !== $siteId && $siteId !== $root['id'])) {
      return null;
    }
    if ($connection->isTransactionActive()) {
      $ids = array_map(static fn (array $row): string => $row['id'], $rows);
      $placeholders = [];
      $parameters = ['organization' => $organizationId];
      foreach ($ids as $index => $id) {
        $key = 'id' . $index;
        $placeholders[] = ':' . $key;
        $parameters[$key] = $id;
      }
      /** @var list<array{id:string,status:string}> $locked */
      $locked = $connection->fetchAllAssociative('SELECT id, status FROM facilities WHERE organization_id = :organization AND id IN (' . implode(', ', $placeholders) . ") AND record_status = 'published' ORDER BY id FOR SHARE", $parameters);
      if (count($locked) !== count($rows)) {
        return null;
      }
      foreach ($locked as $row) {
        $archived = $archived || 'archived' === $row['status'];
      }
    }
    $customer = null === $root['customer_id'] ? null : $this->customers->find($root['customer_id'], $organizationId);
    if (null !== $root['customer_id'] && null === $customer) {
      return null;
    }

    return new ServiceRequestSiteTarget($root['id'], $root['name'], $archived, null === $customer ? null : ['id' => $customer->id, 'name' => $customer->name]);
  }
}
