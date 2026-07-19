<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Organization;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Port\Outbound\InterventionStatisticsPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;
use function max;

/**
 * Adapter InterventionStatisticsAdapter.
 *
 * Implements the Organization module's intervention statistics port
 * using the Intervention module's own persistence records directly
 * (mirrors the Intervention module's other Doctrine adapters, which query
 * `InterventionRecord` through the main entity manager without an
 * intermediate repository indirection).
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionStatisticsAdapter implements InterventionStatisticsPort
{
  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function findRecentInterventions(string $organizationId, int $limit): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    /** @var list<InterventionRecord> $records */
    $records = $this->entityManager->createQueryBuilder()
      ->select('intervention')
      ->from(InterventionRecord::class, 'intervention')
      ->where('intervention.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('intervention.updatedAt', 'DESC')
      ->setMaxResults(max(1, $limit))
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (InterventionRecord $record): RecentInterventionSummary => new RecentInterventionSummary(
        id: $record->id,
        number: $record->number,
        name: $record->name,
        status: $record->status,
        priority: $record->priority,
        siteId: $record->siteId,
        responsibleMemberId: $record->responsibleId,
        dueAt: $record->dueAt,
        updatedAt: $record->updatedAt,
      ),
      $records,
    );
  }
  // #endregion
}
