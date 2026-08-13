<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Statistics;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Statistics\{InterventionIdentifierCount, InterventionStatisticsAggregate};
use Intervention\Application\Port\Outbound\InterventionStatisticsGatewayPort;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;
use function is_numeric;
use function sprintf;

/**
 * Adapter DoctrineInterventionStatisticsGatewayAdapter.
 *
 * Computes the `/interventions/statistics` snapshot in a bounded number of
 * grouped queries against `InterventionRecord` — never one query per status,
 * per priority, per site or per responsible.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionStatisticsGatewayAdapter implements InterventionStatisticsGatewayPort
{
  // #region Constants
  /**
   * The two end states, mirroring
   * `Intervention\Infrastructure\Adapter\Organization\InterventionStatisticsAdapter::CLOSED_STATUSES`
   * (that adapter's `countOverview` establishes this exact "non-terminal"
   * definition for `overdue`; this one reuses it rather than inventing a
   * second one).
   *
   * @var list<string>
   */
  private const array CLOSED_STATUSES = ['published', 'abandoned'];

  /**
   * Statuses where field work is still expected, mirroring
   * `Intervention\Infrastructure\Adapter\Reminder\DoctrineInterventionReminderAdapter::ACTIVE_STATUSES`
   * — `dueSoon` here counts the same population the reminder sweep notifies,
   * minus its anti-spam stamp check (statistics reflect current state, not
   * "still needs a reminder").
   *
   * @var list<string>
   */
  private const array DUE_SOON_STATUSES = ['planned', 'in_progress', 'changes_requested'];

  /**
   * The due-soon window, in hours. Mirrors
   * `Intervention\Application\UseCase\Command\Sweep\SendDueReminders\SendDueRemindersHandler::DUE_SOON_WINDOW_HOURS`,
   * duplicated rather than reused because that constant is `private` on a
   * handler this adapter has no reason to depend on.
   */
  private const int DUE_SOON_WINDOW_HOURS = 48;

  /**
   * Bound on the `bySite` / `byResponsible` breakdowns — a top list, not an
   * unbounded one.
   */
  private const int TOP_N = 10;
  // #endregion

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
  public function aggregate(string $organizationId, DateTimeImmutable $now): InterventionStatisticsAggregate
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $base = $this->entityManager->createQueryBuilder()
      ->from(InterventionRecord::class, 'intervention')
      ->where('intervention.organization = :organization')
      ->setParameter('organization', $organization);

    $total = (int) (clone $base)
      ->select('COUNT(intervention.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<array{status: string, count: int|string}> $statusRows */
    $statusRows = (clone $base)
      ->select('intervention.status AS status', 'COUNT(intervention.id) AS count')
      ->groupBy('intervention.status')
      ->getQuery()
      ->getArrayResult();
    $countsByStatus = [];
    foreach ($statusRows as $row) {
      $countsByStatus[$row['status']] = (int) $row['count'];
    }

    /** @var list<array{priority: string, count: int|string}> $priorityRows */
    $priorityRows = (clone $base)
      ->select('intervention.priority AS priority', 'COUNT(intervention.id) AS count')
      ->groupBy('intervention.priority')
      ->getQuery()
      ->getArrayResult();
    $countsByPriority = [];
    foreach ($priorityRows as $row) {
      $countsByPriority[$row['priority']] = (int) $row['count'];
    }

    $overdue = (int) (clone $base)
      ->select('COUNT(intervention.id)')
      ->andWhere('intervention.status NOT IN (:closed)')
      ->andWhere('intervention.dueAt IS NOT NULL')
      ->andWhere('intervention.dueAt < :now')
      ->setParameter('closed', self::CLOSED_STATUSES)
      ->setParameter('now', $now)
      ->getQuery()
      ->getSingleScalarResult();

    $dueSoonThreshold = $now->modify(sprintf('+%d hours', self::DUE_SOON_WINDOW_HOURS));
    $dueSoon = (int) (clone $base)
      ->select('COUNT(intervention.id)')
      ->andWhere('intervention.status IN (:active)')
      ->andWhere('intervention.dueAt IS NOT NULL')
      ->andWhere('intervention.dueAt >= :now')
      ->andWhere('intervention.dueAt <= :threshold')
      ->setParameter('active', self::DUE_SOON_STATUSES)
      ->setParameter('now', $now)
      ->setParameter('threshold', $dueSoonThreshold)
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<array{id: string, count: int|string}> $siteRows */
    $siteRows = (clone $base)
      ->select('intervention.siteId AS id', 'COUNT(intervention.id) AS count')
      ->andWhere('intervention.siteId IS NOT NULL')
      ->groupBy('intervention.siteId')
      ->orderBy('COUNT(intervention.id)', 'DESC')
      ->setMaxResults(self::TOP_N)
      ->getQuery()
      ->getArrayResult();

    /** @var list<array{id: string, count: int|string}> $responsibleRows */
    $responsibleRows = (clone $base)
      ->select('intervention.responsibleId AS id', 'COUNT(intervention.id) AS count')
      ->andWhere('intervention.responsibleId IS NOT NULL')
      ->groupBy('intervention.responsibleId')
      ->orderBy('COUNT(intervention.id)', 'DESC')
      ->setMaxResults(self::TOP_N)
      ->getQuery()
      ->getArrayResult();

    return new InterventionStatisticsAggregate(
      total: $total,
      countsByStatus: $countsByStatus,
      countsByPriority: $countsByPriority,
      overdue: $overdue,
      dueSoon: $dueSoon,
      topSites: $this->identifierCounts($siteRows),
      topResponsibles: $this->identifierCounts($responsibleRows),
      averagePublicationDays: $this->averagePublicationDays($organizationId),
    );
  }
  // #endregion

  // #region Internals
  /**
   * Method identifierCounts.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, count: int|string}> $rows the raw grouped-count rows
   *
   * @return list<InterventionIdentifierCount> the mapped identifier counts
   */
  private function identifierCounts(array $rows): array
  {
    return array_map(
      static fn (array $row): InterventionIdentifierCount => new InterventionIdentifierCount($row['id'], (int) $row['count']),
      $rows,
    );
  }

  /**
   * Method averagePublicationDays.
   *
   * `published` is a terminal, immutable status
   * (`InterventionStatus::isMutable()` is `false` for it, and every mutating
   * write path rejects a published intervention), so `updated_at` can never
   * move again once publication is reached — it IS the publish instant, and
   * `created_at` IS the draft-creation instant (every intervention starts as
   * `draft` on `POST /interventions`, see `MutateInterventionWorkflowHandler`).
   *
   * Computed with one native SQL round trip rather than DQL: Doctrine's DQL
   * has no `EXTRACT`/`EPOCH` function, and pulling every published
   * intervention's two timestamps to average them in PHP would not scale past
   * a handful of organizations the way a single `AVG()` does.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return ?float the mean days between draft creation and publication, or null when none are published
   */
  private function averagePublicationDays(string $organizationId): ?float
  {
    $sql = <<<'SQL'
      SELECT AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400.0) AS avg_days
      FROM interventions
      WHERE organization_id = :organizationId AND status = :status
      SQL;

    $value = $this->entityManager->getConnection()->fetchOne($sql, [
      'organizationId' => $organizationId,
      'status' => 'published',
    ]);

    return is_numeric($value) ? (float) $value : null;
  }
  // #endregion
}
