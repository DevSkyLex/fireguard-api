<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Organization;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Application\Contract\Maintenance\MaintenanceDueSummary;
use Organization\Application\Port\Outbound\MaintenanceStatisticsPort;

use function array_map;
use function max;

/**
 * Adapter MaintenanceStatisticsAdapter.
 *
 * Implements the Organization module's maintenance statistics port with a
 * direct read over the Maintenance schedule records — mirrors
 * `Intervention\Infrastructure\Adapter\Organization\InterventionStatisticsAdapter`.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceStatisticsAdapter implements MaintenanceStatisticsPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the MAIN database entity manager
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
  public function countDueOverview(string $organizationId, DateTimeImmutable $now, DateTimeImmutable $windowEnd): array
  {
    $base = $this->entityManager->createQueryBuilder()
      ->select('COUNT(schedule.id)')
      ->from(MaintenanceScheduleRecord::class, 'schedule')
      ->where('IDENTITY(schedule.organization) = :organizationId')
      ->andWhere('schedule.nextDueAt IS NOT NULL')
      ->setParameter('organizationId', $organizationId);

    $overdue = (int) (clone $base)
      ->andWhere('schedule.nextDueAt < :now')
      ->setParameter('now', $now)
      ->getQuery()
      ->getSingleScalarResult();

    $dueSoon = (int) (clone $base)
      ->andWhere('schedule.nextDueAt >= :now')
      ->andWhere('schedule.nextDueAt <= :windowEnd')
      ->setParameter('now', $now)
      ->setParameter('windowEnd', $windowEnd)
      ->getQuery()
      ->getSingleScalarResult();

    return ['due_soon' => $dueSoon, 'overdue' => $overdue];
  }

  /**
   * {@inheritDoc}
   */
  public function findDueSchedules(string $organizationId, DateTimeImmutable $now, DateTimeImmutable $windowEnd, int $limit): array
  {
    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $this->entityManager->createQueryBuilder()
      ->select('schedule')
      ->from(MaintenanceScheduleRecord::class, 'schedule')
      ->where('IDENTITY(schedule.organization) = :organizationId')
      ->andWhere('schedule.nextDueAt IS NOT NULL')
      ->andWhere('schedule.nextDueAt <= :windowEnd')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('windowEnd', $windowEnd)
      ->orderBy('schedule.nextDueAt', 'ASC')
      ->setMaxResults(max(1, $limit))
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (MaintenanceScheduleRecord $record): MaintenanceDueSummary => new MaintenanceDueSummary(
        equipmentId: $record->equipmentId,
        facilityId: $record->facilityId,
        equipmentType: $record->equipmentType,
        nextDueAt: $record->nextDueAt ?? $now,
        overdue: null !== $record->nextDueAt && $record->nextDueAt < $now,
      ),
      $records,
    );
  }
  // #endregion
}
