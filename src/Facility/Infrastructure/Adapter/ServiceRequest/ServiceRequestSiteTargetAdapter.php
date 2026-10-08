<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\ServiceRequest;

use Customer\Application\Port\Inbound\CustomerLookupPort;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityHierarchyPort;
use ServiceRequest\Application\Contract\Target\ServiceRequestSiteTarget;
use ServiceRequest\Application\Port\Outbound\ServiceRequestSiteTargetPort;

use function count;
use function implode;

/**
 * Class ServiceRequestSiteTargetAdapter
 *
 * Resolves published ancestry and minimal customer identity on main.
 *
 * @category Adapter
 *
 * @phpstan-type AncestryRow array{id:string,name:string,type:string,parent_facility_id:?string,customer_id:?string,status:string}
 */
final readonly class ServiceRequestSiteTargetAdapter implements ServiceRequestSiteTargetPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly wired main manager
   * @param CustomerLookupPort $customers organization-scoped customer identities
   * @param FacilityHierarchyPort $hierarchy shared organization hierarchy lock
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager, private CustomerLookupPort $customers, private FacilityHierarchyPort $hierarchy)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method lock
   *
   * Acquires the shared hierarchy lock before callers resolve and lock their target.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   *
   * @return void
   */
  public function lock(string $organizationId): void
  {
    $this->hierarchy->lock($organizationId);
  }

  /**
   * Method find
   *
   * Resolves the selected facility's published root site within one organization.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string|null $siteId optional root site constraint
   * @param string|null $facilityId preferred target whose ancestry is resolved
   *
   * @return ServiceRequestSiteTarget|null the scoped target, or null when unavailable
   */
  public function find(string $organizationId, ?string $siteId, ?string $facilityId): ?ServiceRequestSiteTarget
  {
    $targetId = $facilityId ?? $siteId;
    if (null === $targetId) {
      return null;
    }
    $connection = $this->entityManager->getConnection();
    $rows = $this->publishedAncestry($connection, $organizationId, $targetId);
    $root = $this->matchingRootSite($rows, $siteId);

    return null === $root ? null : $this->siteTarget($connection, $organizationId, $rows, $root);
  }

  /**
   * Method publishedAncestry
   *
   * Fences every recursive edge by organization and publication, with cycle and depth bounds.
   *
   * @access private
   *
   * @param Connection $connection owning main connection
   * @param string $organizationId owning organization
   * @param string $targetId selected facility
   *
   * @return list<AncestryRow> the available ancestry
   */
  private function publishedAncestry(Connection $connection, string $organizationId, string $targetId): array
  {
    /** @var list<AncestryRow> */
    return $connection->fetchAllAssociative(<<<'SQL'
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
  }

  /**
   * Method matchingRootSite
   *
   * Requires an actual root site and preserves an explicit site constraint.
   *
   * @access private
   *
   * @param list<AncestryRow> $rows available ancestry
   * @param string|null $siteId optional root site constraint
   *
   * @return AncestryRow|null the matching root
   */
  private function matchingRootSite(array $rows, ?string $siteId): ?array
  {
    $root = null;
    foreach ($rows as $row) {
      if ('site' === $row['type'] && null === $row['parent_facility_id']) {
        $root = $row;
      }
    }
    if (null !== $root && null !== $siteId && $siteId !== $root['id']) {
      return null;
    }

    return $root;
  }

  /**
   * Method siteTarget
   *
   * Resolves customer identity only after the ancestry has passed its locked recheck.
   *
   * @access private
   *
   * @param Connection $connection owning main connection
   * @param string $organizationId owning organization
   * @param list<AncestryRow> $rows resolved ancestry
   * @param AncestryRow $root matching root site
   *
   * @return ServiceRequestSiteTarget|null the target, or null when a retained identity is unavailable
   */
  private function siteTarget(Connection $connection, string $organizationId, array $rows, array $root): ?ServiceRequestSiteTarget
  {
    $archived = $this->ancestryArchived($connection, $organizationId, $rows);
    if (null === $archived) {
      return null;
    }
    $customer = null === $root['customer_id'] ? null : $this->customers->find($root['customer_id'], $organizationId);
    if (null !== $root['customer_id'] && null === $customer) {
      return null;
    }

    return new ServiceRequestSiteTarget($root['id'], $root['name'], $archived, null === $customer ? null : ['id' => $customer->id, 'name' => $customer->name]);
  }

  /**
   * Method ancestryArchived
   *
   * Retains initially observed archives and catches unavailable or archived rows after locking.
   *
   * @access private
   *
   * @param Connection $connection owning main connection
   * @param string $organizationId owning organization
   * @param list<AncestryRow> $rows initially resolved ancestry
   *
   * @return bool|null the archival state, or null when a row became unavailable
   */
  private function ancestryArchived(Connection $connection, string $organizationId, array $rows): ?bool
  {
    $archived = $this->hasArchived($rows);
    if (!$connection->isTransactionActive()) {
      return $archived;
    }
    $locked = $this->lockAncestry($connection, $organizationId, $rows);
    if (count($locked) !== count($rows)) {
      return null;
    }

    return $archived || $this->hasArchived($locked);
  }

  /**
   * Method lockAncestry
   *
   * Locks published rows in the existing identifier order on the owning transaction.
   *
   * @access private
   *
   * @param Connection $connection owning main connection
   * @param string $organizationId owning organization
   * @param list<AncestryRow> $rows initially resolved ancestry
   *
   * @return list<array{id:string,status:string}> rows still available under the shared lock
   */
  private function lockAncestry(Connection $connection, string $organizationId, array $rows): array
  {
    $placeholders = [];
    $parameters = ['organization' => $organizationId];
    foreach ($rows as $index => $row) {
      $key = 'id' . $index;
      $placeholders[] = ':' . $key;
      $parameters[$key] = $row['id'];
    }

    /** @var list<array{id:string,status:string}> */
    return $connection->fetchAllAssociative('SELECT id, status FROM facilities WHERE organization_id = :organization AND id IN (' . implode(', ', $placeholders) . ") AND record_status = 'published' ORDER BY id FOR SHARE", $parameters);
  }

  /**
   * Method hasArchived
   *
   * Marks a target archived when any observed ancestor is archived.
   *
   * @access private
   *
   * @param list<array{status:string}> $rows ancestry statuses
   *
   * @return bool whether an archive was observed
   */
  private function hasArchived(array $rows): bool
  {
    foreach ($rows as $row) {
      if ('archived' === $row['status']) {
        return true;
      }
    }

    return false;
  }
  // #endregion
}
