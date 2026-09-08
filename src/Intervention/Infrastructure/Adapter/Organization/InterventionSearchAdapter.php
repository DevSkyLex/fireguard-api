<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Organization;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\Port\Outbound\InterventionSearchPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function ctype_digit;
use function max;
use function sprintf;

/**
 * Adapter InterventionSearchAdapter.
 *
 * Implements the Organization module's intervention search port with a
 * single bounded query over `InterventionRecord`: ILIKE on the name, plus
 * an exact match on the intervention number when the term is all digits
 * (the number is an integer column — a substring match on it would be a
 * cast, not a search).
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionSearchAdapter implements InterventionSearchPort
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
      ->select('intervention')
      ->from(InterventionRecord::class, 'intervention')
      ->where('intervention.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('intervention.updatedAt', 'DESC')
      ->setMaxResults(max(1, $limit));

    $predicate = "LOWER(intervention.name) LIKE :searchTerm ESCAPE '\\'";
    if (ctype_digit($term)) {
      $predicate .= ' OR intervention.number = :searchNumber';
      $queryBuilder->setParameter('searchNumber', (int) $term);
    }

    $queryBuilder
      ->andWhere('(' . $predicate . ')')
      ->setParameter('searchTerm', TrigramSearchExpression::likeValue($term));

    /** @var list<InterventionRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    $hits = [];
    foreach ($records as $record) {
      $hits[] = new OrganizationSearchHit(
        id: $record->id,
        title: $record->name,
        subtitle: sprintf('#%d', $record->number),
      );
    }

    return $hits;
  }
  // #endregion
}
