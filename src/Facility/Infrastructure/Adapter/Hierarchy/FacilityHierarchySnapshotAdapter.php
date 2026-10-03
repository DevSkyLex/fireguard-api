<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Hierarchy;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Port\Outbound\FacilityHierarchySnapshotPort;
use LogicException;

/**
 * Adapter FacilityHierarchySnapshotAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityHierarchySnapshotAdapter implements FacilityHierarchySnapshotPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method load.
   *
   * @since 1.0.0
   *
   * @return array<string, FacilityHierarchyNode> scalar graph snapshot
   */
  public function load(string $organizationId): array
  {
    /** @var list<array{id: string, type: string, parent_facility_id: ?string, status: string, record_status: string, intervention_id: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative(
      'SELECT id, type, parent_facility_id, status, record_status, intervention_id FROM facilities WHERE organization_id = :organization',
      ['organization' => $organizationId],
    );
    $nodes = [];
    foreach ($rows as $row) {
      $id = (string) $row['id'];
      $nodes[$id] = new FacilityHierarchyNode(
        $id,
        (string) $row['type'],
        null === $row['parent_facility_id'] ? null : (string) $row['parent_facility_id'],
        (string) $row['status'],
        (string) $row['record_status'],
        null === $row['intervention_id'] ? null : (string) $row['intervention_id'],
      );
    }

    return $nodes;
  }

  /**
   * Method lock.
   *
   * @since 1.0.0
   */
  public function lock(string $organizationId): void
  {
    $connection = $this->entityManager->getConnection();
    if (!$connection->isTransactionActive()) {
      throw new LogicException('Facility hierarchy locks require an active main transaction.');
    }
    $connection->executeQuery(
      'SELECT pg_advisory_xact_lock(hashtextextended(:scope, 0))',
      ['scope' => 'facility-hierarchy:' . $organizationId],
    )->free();
  }
  // #endregion
}
