<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Maintenance;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Maintenance\Application\Port\Outbound\Naming\MaintenanceFacilityNamingPort;

/**
 * Adapter FacilityMaintenanceNamingAdapter.
 *
 * Facility-side implementation of the Maintenance module's naming contract:
 * resolves facility identifiers into display names in a single query.
 * Mirrors `Facility\Infrastructure\Adapter\Inspection\FacilityNamingAdapter`.
 *
 * Reads the Doctrine record directly rather than hydrating domain aggregates
 * through the repository: this is a read-side label lookup over a page of
 * rows, and rebuilding every facility aggregate to read its name would cost
 * far more than the answer is worth.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMaintenanceNamingAdapter implements MaintenanceFacilityNamingPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method findNamesByIds.
   * {@inheritdoc}
   *
   * @since 1.0.0
   *
   * @param list<string> $facilityIds the facility identifiers to resolve
   *
   * @return array<string, string> display name keyed by facility identifier
   */
  public function findNamesByIds(array $facilityIds): array
  {
    if ([] === $facilityIds) {
      return [];
    }

    /** @var list<array{id: string, name: string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('facility.id AS id', 'facility.name AS name')
      ->from(FacilityRecord::class, 'facility')
      ->where('facility.id IN (:facilityIds)')
      ->setParameter('facilityIds', $facilityIds)
      ->getQuery()
      ->getArrayResult();

    $names = [];
    foreach ($rows as $row) {
      $names[$row['id']] = $row['name'];
    }

    return $names;
  }
  // #endregion
}
