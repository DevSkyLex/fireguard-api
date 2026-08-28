<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Statistics;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Inspection\Application\Contract\Statistics\{NonConformityEquipmentTypeCount, NonConformityFacilityCount, NonConformitySeverityBucket, NonConformityStatisticsAggregate};
use Inspection\Application\Port\Outbound\NonConformityStatisticsGatewayPort;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, NonConformityRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function in_array;
use function is_numeric;

/**
 * Adapter DoctrineNonConformityStatisticsGatewayAdapter.
 *
 * Computes the `/organizations/{organizationId}/non-conformities/statistics`
 * snapshot in a bounded number of grouped queries — one GROUP BY per
 * breakdown, one scalar count, and one native aggregate for the resolution
 * metrics. Organization scoping always goes through the owning inspection's
 * join; the optional window applies to the non-conformity's `createdAt`.
 *
 * The resolution metrics run as native SQL: DQL has no interval arithmetic
 * and no `PERCENTILE_CONT`, while PostgreSQL — the only platform this suite
 * runs on — answers both in one aggregate pass, which is why `medianDays`
 * is included rather than omitted.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineNonConformityStatisticsGatewayAdapter implements NonConformityStatisticsGatewayPort
{
  // #region Constants
  /**
   * The unresolved statuses, mirroring
   * `NonConformityRepository::countSlaBreachedByOrganizationId()` and the
   * navigation counters' open set.
   *
   * @var list<string>
   */
  private const array OPEN_STATUSES = ['open', 'in_progress'];

  /**
   * Bound on the `byFacility` / `byEquipmentType` breakdowns — a top list,
   * not an unbounded one.
   */
  private const int TOP_N = 10;
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
  public function aggregate(string $organizationId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): NonConformityStatisticsAggregate
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $base = $this->entityManager->createQueryBuilder()
      ->from(NonConformityRecord::class, 'nonConformity')
      ->join('nonConformity.inspection', 'inspection')
      ->where('inspection.organization = :organization')
      ->setParameter('organization', $organization);
    $this->applyWindow($base, $from, $to);

    /** @var list<array{severity: string, status: string, count: int|string}> $severityRows */
    $severityRows = (clone $base)
      ->select('nonConformity.severity AS severity', 'nonConformity.status AS status', 'COUNT(nonConformity.id) AS count')
      ->groupBy('nonConformity.severity', 'nonConformity.status')
      ->getQuery()
      ->getArrayResult();

    $openBySeverity = [];
    $resolvedBySeverity = [];
    foreach ($severityRows as $row) {
      if (in_array($row['status'], self::OPEN_STATUSES, true)) {
        $openBySeverity[$row['severity']] = ($openBySeverity[$row['severity']] ?? 0) + (int) $row['count'];
      } else {
        $resolvedBySeverity[$row['severity']] = ($resolvedBySeverity[$row['severity']] ?? 0) + (int) $row['count'];
      }
    }

    $bySeverity = [];
    foreach ($openBySeverity + $resolvedBySeverity as $severity => $unused) {
      $bySeverity[$severity] = new NonConformitySeverityBucket(
        open: $openBySeverity[$severity] ?? 0,
        resolved: $resolvedBySeverity[$severity] ?? 0,
      );
    }

    /** @var list<array{facilityId: string, open: int|string, critical: int|string}> $facilityRows */
    $facilityRows = (clone $base)
      ->select(
        'inspection.facilityId AS facilityId',
        'COUNT(nonConformity.id) AS open',
        "SUM(CASE WHEN nonConformity.severity = 'critical' THEN 1 ELSE 0 END) AS critical",
      )
      ->andWhere('nonConformity.status IN (:openStatuses)')
      ->andWhere('inspection.facilityId IS NOT NULL')
      ->setParameter('openStatuses', self::OPEN_STATUSES)
      ->groupBy('inspection.facilityId')
      ->orderBy('open', 'DESC')
      ->addOrderBy('facilityId', 'ASC')
      ->setMaxResults(self::TOP_N)
      ->getQuery()
      ->getArrayResult();

    $topFacilities = [];
    foreach ($facilityRows as $row) {
      $topFacilities[] = new NonConformityFacilityCount(
        facilityId: $row['facilityId'],
        open: (int) $row['open'],
        critical: (int) $row['critical'],
      );
    }

    /** @var list<array{type: string, open: int|string}> $equipmentTypeRows */
    $equipmentTypeRows = (clone $base)
      ->select('equipment.type AS type', 'COUNT(nonConformity.id) AS open')
      ->join(EquipmentRecord::class, 'equipment', 'WITH', 'equipment.id = inspection.equipmentId')
      ->andWhere('nonConformity.status IN (:openStatuses)')
      ->setParameter('openStatuses', self::OPEN_STATUSES)
      ->groupBy('equipment.type')
      ->orderBy('open', 'DESC')
      ->addOrderBy('type', 'ASC')
      ->setMaxResults(self::TOP_N)
      ->getQuery()
      ->getArrayResult();

    $topEquipmentTypes = [];
    foreach ($equipmentTypeRows as $row) {
      $topEquipmentTypes[] = new NonConformityEquipmentTypeCount(
        type: $row['type'],
        open: (int) $row['open'],
      );
    }

    $slaBreachedOpen = (int) (clone $base)
      ->select('COUNT(nonConformity.id)')
      ->andWhere('nonConformity.status IN (:openStatuses)')
      ->andWhere('nonConformity.slaBreachNotifiedAt IS NOT NULL')
      ->setParameter('openStatuses', self::OPEN_STATUSES)
      ->getQuery()
      ->getSingleScalarResult();

    [$averageResolutionDays, $medianResolutionDays] = $this->resolutionMetrics($organizationId, $from, $to);

    return new NonConformityStatisticsAggregate(
      bySeverity: $bySeverity,
      topFacilities: $topFacilities,
      topEquipmentTypes: $topEquipmentTypes,
      averageResolutionDays: $averageResolutionDays,
      medianResolutionDays: $medianResolutionDays,
      slaBreachedOpen: $slaBreachedOpen,
    );
  }

  /**
   * Method applyWindow.
   *
   * @since 1.0.0
   *
   * @param QueryBuilder $queryBuilder the builder to mutate
   * @param ?DateTimeImmutable $from inclusive `createdAt` lower bound
   * @param ?DateTimeImmutable $to inclusive `createdAt` upper bound
   */
  private function applyWindow(QueryBuilder $queryBuilder, ?DateTimeImmutable $from, ?DateTimeImmutable $to): void
  {
    if (null !== $from) {
      $queryBuilder->andWhere('nonConformity.createdAt >= :windowFrom')->setParameter('windowFrom', $from);
    }
    if (null !== $to) {
      $queryBuilder->andWhere('nonConformity.createdAt <= :windowTo')->setParameter('windowTo', $to);
    }
  }

  /**
   * Method resolutionMetrics.
   *
   * One native aggregate pass over the resolved rows: mean and median of
   * `(resolved_at - created_at)` in fractional days.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?DateTimeImmutable $from inclusive `createdAt` lower bound
   * @param ?DateTimeImmutable $to inclusive `createdAt` upper bound
   *
   * @return array{0: ?float, 1: ?float} average and median resolution days, null when no row resolved
   */
  private function resolutionMetrics(string $organizationId, ?DateTimeImmutable $from, ?DateTimeImmutable $to): array
  {
    $nonConformityTable = $this->entityManager->getClassMetadata(NonConformityRecord::class)->getTableName();
    $inspectionTable = $this->entityManager->getClassMetadata(InspectionRecord::class)->getTableName();

    $sql = 'SELECT'
      . ' AVG(EXTRACT(EPOCH FROM (nc.resolved_at - nc.created_at)) / 86400.0) AS average_days,'
      . ' PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (nc.resolved_at - nc.created_at)) / 86400.0) AS median_days'
      . ' FROM ' . $nonConformityTable . ' nc'
      . ' INNER JOIN ' . $inspectionTable . ' i ON i.id = nc.inspection_id'
      . ' WHERE i.organization_id = :organizationId'
      . ' AND nc.resolved_at IS NOT NULL';
    $parameters = ['organizationId' => $organizationId];

    if (null !== $from) {
      $sql .= ' AND nc.created_at >= :windowFrom';
      $parameters['windowFrom'] = $from->format('Y-m-d H:i:s.u');
    }
    if (null !== $to) {
      $sql .= ' AND nc.created_at <= :windowTo';
      $parameters['windowTo'] = $to->format('Y-m-d H:i:s.u');
    }

    /** @var array{average_days: mixed, median_days: mixed}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative($sql, $parameters);
    if (false === $row) {
      return [null, null];
    }

    return [
      is_numeric($row['average_days']) ? (float) $row['average_days'] : null,
      is_numeric($row['median_days']) ? (float) $row['median_days'] : null,
    ];
  }
  // #endregion
}
