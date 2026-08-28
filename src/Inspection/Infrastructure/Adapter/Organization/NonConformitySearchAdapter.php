<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Organization;

use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\NonConformityRecord;
use Organization\Application\Contract\Search\OrganizationSearchHit;
use Organization\Application\Port\Outbound\NonConformitySearchPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function max;
use function mb_strlen;
use function mb_substr;
use function rtrim;

/**
 * Adapter NonConformitySearchAdapter.
 *
 * Implements the Organization module's non-conformity search port with a
 * single bounded ILIKE query over `NonConformityRecord` descriptions,
 * organization-scoped through the owning inspection's join.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformitySearchAdapter implements NonConformitySearchPort
{
  // #region Constants
  /**
   * Constant TITLE_MAX_LENGTH.
   *
   * A non-conformity description is free text of unbounded length; the
   * search hit title is a display line, so it is truncated here with an
   * ellipsis rather than shipping paragraphs to a dropdown.
   *
   * @since 1.0.0
   */
  private const int TITLE_MAX_LENGTH = 120;
  // #endregion

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
      ->select('nonConformity')
      ->from(NonConformityRecord::class, 'nonConformity')
      ->join('nonConformity.inspection', 'inspection')
      ->where('inspection.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('nonConformity.updatedAt', 'DESC')
      ->setMaxResults(max(1, $limit));

    TrigramSearchExpression::apply(
      $queryBuilder,
      'searchTerm',
      $term,
      'nonConformity.description',
    );

    /** @var list<NonConformityRecord> $records */
    $records = $queryBuilder->getQuery()->getResult();

    $hits = [];
    foreach ($records as $record) {
      $hits[] = new OrganizationSearchHit(
        id: $record->id,
        title: $this->truncate($record->description),
        subtitle: $record->severity,
        extra: $record->status,
      );
    }

    return $hits;
  }

  /**
   * Method truncate.
   *
   * @since 1.0.0
   *
   * @param string $text the raw description
   *
   * @return string the display-safe title
   */
  private function truncate(string $text): string
  {
    if (mb_strlen($text) <= self::TITLE_MAX_LENGTH) {
      return $text;
    }

    return rtrim(mb_substr($text, 0, self::TITLE_MAX_LENGTH - 1)) . '…';
  }
  // #endregion
}
