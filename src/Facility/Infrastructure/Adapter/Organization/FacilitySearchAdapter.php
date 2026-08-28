<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Organization;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\Port\Outbound\FacilitySearchPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function max;

/**
 * Adapter FacilitySearchAdapter.
 *
 * Implements the Organization module's facility search port with a single
 * bounded ILIKE query over `FacilityRecord` (name, code, address),
 * published records only — mirroring {@see FacilityStatisticsAdapter}'s
 * host/adapter split.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilitySearchAdapter implements FacilitySearchPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
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
  public function search(string $organizationId, string $term, int $limit): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $queryBuilder = $this->entityManager->createQueryBuilder()
      ->select('facility')
      ->from(FacilityRecord::class, 'facility')
      ->where('facility.organization = :organization')
      ->andWhere('facility.recordStatus = :publishedRecordStatus')
      ->setParameter('organization', $organization)
      ->setParameter('publishedRecordStatus', 'published')
      ->orderBy('facility.updatedAt', 'DESC')
      ->setMaxResults(max(1, $limit));

    TrigramSearchExpression::apply(
      $queryBuilder,
      'searchTerm',
      $term,
      'facility.name',
      'facility.code',
      'facility.address',
    );

    /** @var list<FacilityRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    $hits = [];
    foreach ($records as $record) {
      $hits[] = new OrganizationSearchHit(
        id: $record->id,
        title: $record->name,
        subtitle: $record->code,
        extra: $record->address,
      );
    }

    return $hits;
  }
  // #endregion
}
