<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Port\Outbound\InterventionSiteNamingPort;

/**
 * Adapter InterventionSiteNamingAdapter.
 *
 * Facility-side implementation of the Intervention module's site naming
 * contract: resolves facility identifiers into display names in a single
 * query. Mirrors `Facility\Infrastructure\Adapter\Equipment\FacilityNamingAdapter`.
 *
 * Reads the Doctrine record directly rather than hydrating domain aggregates
 * through the repository: this is a read-side label lookup over a bounded
 * top-10 list, and rebuilding every facility aggregate to read its name would
 * cost far more than the answer is worth.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionSiteNamingAdapter implements InterventionSiteNamingPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function findNamesByIds(string $organizationId, array $siteIds): array
  {
    if ([] === $siteIds) {
      return [];
    }

    /** @var list<array{id: string, name: string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('facility.id AS id', 'facility.name AS name')
      ->from(FacilityRecord::class, 'facility')
      ->where('facility.id IN (:siteIds)')
      ->andWhere('facility.organization = :organizationId')
      ->setParameter('siteIds', $siteIds)
      ->setParameter('organizationId', $organizationId)
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
