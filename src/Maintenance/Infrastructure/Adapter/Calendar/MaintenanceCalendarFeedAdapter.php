<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Calendar;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Feed\MaintenanceCalendarFeedPort;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;
use function max;

/**
 * Adapter MaintenanceCalendarFeedAdapter.
 *
 * Implements the Calendar module's maintenance feed port by querying
 * `MaintenanceScheduleRecord` directly through the main entity manager,
 * mirroring `EquipmentMaintenanceDueStatusAdapter` (another cross-module
 * adapter hosted in this module that queries the record directly rather
 * than through {@see \Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort},
 * whose `list()`/`listDueForCampaign()` methods do not offer a plain
 * `nextDueAt` range across every due status).
 *
 * Every due status (not just `due_soon`/`overdue`) is included: navigating
 * the calendar to a past date range should still surface schedules that were
 * due then, whatever their current status.
 *
 * `title` is a source-neutral phrase (no equipment name is resolved here —
 * resolving one would mean a per-row cross-module call into Equipment, the
 * exact N+1 this adapter must avoid); `status` is the schedule's raw
 * `dueStatus` value — the frontend owns labels/colors.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceCalendarFeedAdapter implements MaintenanceCalendarFeedPort
{
  // #region Constants
  private const string SOURCE_KEY = 'maintenance';

  private const string TARGET_TYPE = 'maintenance_schedule';

  private const string TITLE = 'Preventive maintenance due';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function findBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $this->entityManager->createQueryBuilder()
      ->select('schedule')
      ->from(MaintenanceScheduleRecord::class, 'schedule')
      ->where('schedule.organization = :organization')
      ->andWhere('schedule.nextDueAt IS NOT NULL')
      ->andWhere('schedule.nextDueAt BETWEEN :from AND :to')
      ->setParameter('organization', $organization)
      ->setParameter('from', $from)
      ->setParameter('to', $to)
      ->orderBy('schedule.nextDueAt', 'ASC')
      ->addOrderBy('schedule.id', 'ASC')
      ->setMaxResults(max(1, $limit))
      ->getQuery()
      ->getResult();

    return array_map($this->toFeedItem(...), $records);
  }

  /**
   * Method toFeedItem.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRecord $record the maintenance schedule record
   *
   * @return CalendarFeedItem the mapped feed item
   */
  private function toFeedItem(MaintenanceScheduleRecord $record): CalendarFeedItem
  {
    /** @var DateTimeImmutable $nextDueAt guaranteed non-null by the WHERE clause */
    $nextDueAt = $record->nextDueAt;

    return new CalendarFeedItem(
      sourceKey: self::SOURCE_KEY,
      id: $record->id,
      title: self::TITLE,
      description: null,
      startsAt: $nextDueAt,
      endsAt: null,
      allDay: true,
      facilityId: $record->facilityId,
      status: $record->dueStatus,
      targetType: self::TARGET_TYPE,
      targetId: $record->id,
    );
  }
  // #endregion
}
