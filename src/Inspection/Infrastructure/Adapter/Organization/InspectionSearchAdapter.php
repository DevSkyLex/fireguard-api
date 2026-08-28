<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Organization;

use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistRecord, InspectionRecord};
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\Port\Outbound\InspectionSearchPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function max;

/**
 * Adapter InspectionSearchAdapter.
 *
 * Implements the Organization module's inspection search port with a single
 * bounded query over `InspectionRecord` (published records only), matching
 * the joined checklist's reference code or the inspection identifier — the
 * two handles a user actually quotes when hunting for an inspection.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionSearchAdapter implements InspectionSearchPort
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
      ->select('inspection', 'checklist.referenceCode AS checklistReferenceCode')
      ->from(InspectionRecord::class, 'inspection')
      ->leftJoin(ChecklistRecord::class, 'checklist', 'WITH', 'checklist.id = inspection.checklistId')
      ->where('inspection.organization = :organization')
      ->andWhere('inspection.recordStatus = :publishedRecordStatus')
      ->andWhere("(LOWER(checklist.referenceCode) LIKE :searchTerm ESCAPE '\\' OR LOWER(inspection.id) LIKE :searchTerm ESCAPE '\\')")
      ->setParameter('organization', $organization)
      ->setParameter('publishedRecordStatus', 'published')
      ->setParameter('searchTerm', TrigramSearchExpression::likeValue($term))
      ->orderBy('inspection.updatedAt', 'DESC')
      ->setMaxResults(max(1, $limit));

    /** @var list<array{0: InspectionRecord, checklistReferenceCode: ?string}> $rows */
    $rows = $queryBuilder->getQuery()->getResult();

    $hits = [];
    foreach ($rows as $row) {
      $record = $row[0];
      $referenceCode = $row['checklistReferenceCode'];

      $hits[] = new OrganizationSearchHit(
        id: $record->id,
        title: null !== $referenceCode && '' !== $referenceCode ? $referenceCode : $record->id,
        subtitle: $record->status,
        extra: $record->result,
      );
    }

    return $hits;
  }
  // #endregion
}
