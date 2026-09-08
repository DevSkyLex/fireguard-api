<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Organization;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\Port\Outbound\EquipmentSearchPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function max;
use function trim;

/**
 * Adapter EquipmentSearchAdapter.
 *
 * Implements the Organization module's equipment search port with a single
 * bounded ILIKE query over `EquipmentRecord` (type, brand, model, serial
 * number, location label), published records only — mirroring
 * {@see EquipmentStatisticsAdapter}'s host/adapter split.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentSearchAdapter implements EquipmentSearchPort
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
      ->select('equipment')
      ->from(EquipmentRecord::class, 'equipment')
      ->where('equipment.organization = :organization')
      ->andWhere('equipment.recordStatus = :publishedRecordStatus')
      ->setParameter('organization', $organization)
      ->setParameter('publishedRecordStatus', 'published')
      ->orderBy('equipment.updatedAt', 'DESC')
      ->setMaxResults(max(1, $limit));

    TrigramSearchExpression::apply(
      $queryBuilder,
      'searchTerm',
      $term,
      'equipment.type',
      'equipment.brand',
      'equipment.model',
      'equipment.serialNumber',
      'equipment.locationLabel',
    );

    /** @var list<EquipmentRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    $hits = [];
    foreach ($records as $record) {
      $brandModel = trim(($record->brand ?? '') . ' ' . ($record->model ?? ''));

      $hits[] = new OrganizationSearchHit(
        id: $record->id,
        title: '' !== $brandModel ? $brandModel : $record->type,
        subtitle: $record->serialNumber,
        extra: $record->locationLabel,
      );
    }

    return $hits;
  }
  // #endregion
}
