<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Calendar;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Application\Port\Outbound\Feed\InterventionCalendarFeedPort;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;
use function max;

/**
 * Adapter InterventionCalendarFeedAdapter.
 *
 * Implements the Calendar module's intervention feed port using the
 * Intervention module's own persistence records directly (mirrors
 * `InterventionStatisticsAdapter`, which queries `InterventionRecord`
 * through the main entity manager without an intermediate repository
 * indirection).
 *
 * An intervention occurs on the calendar when either its `plannedStartAt` or
 * its `dueAt` falls within the requested range; the resolved occurrence
 * instant is `COALESCE(plannedStartAt, dueAt)` (`plannedStartAt` wins when
 * both are set), with `dueAt` surfaced as `endsAt` only when it differs from
 * the resolved start. Interventions with neither date set never appear.
 *
 * `title` is the intervention's own free-form name (raw user-entered data);
 * `status` is its raw status value — the frontend owns labels/colors.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionCalendarFeedAdapter implements InterventionCalendarFeedPort
{
  // #region Constants
  private const string SOURCE_KEY = 'intervention';

  private const string TARGET_TYPE = 'intervention';
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

    // DQL's `ORDER BY` grammar does not accept a bare `COALESCE(...)` call
    // (only path expressions, arithmetic expressions, and result variables
    // are accepted there) — it must first be projected as a `HIDDEN` result
    // variable in the `SELECT` clause, then referenced by that alias in
    // `ORDER BY`. `HIDDEN` keeps `getResult()` returning plain
    // `InterventionRecord` objects (never a mixed entity/scalar array).
    /** @var list<InterventionRecord> $records */
    $records = $this->entityManager->createQueryBuilder()
      ->select('intervention')
      ->addSelect('COALESCE(intervention.plannedStartAt, intervention.dueAt) AS HIDDEN occurrenceInstant')
      ->from(InterventionRecord::class, 'intervention')
      ->where('intervention.organization = :organization')
      ->andWhere(
        '(intervention.plannedStartAt IS NOT NULL AND intervention.plannedStartAt BETWEEN :from AND :to)'
        . ' OR (intervention.dueAt IS NOT NULL AND intervention.dueAt BETWEEN :from AND :to)',
      )
      ->setParameter('organization', $organization)
      ->setParameter('from', $from)
      ->setParameter('to', $to)
      ->orderBy('occurrenceInstant', 'ASC')
      ->addOrderBy('intervention.id', 'ASC')
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
   * @param InterventionRecord $record the intervention record
   *
   * @return CalendarFeedItem the mapped feed item
   */
  private function toFeedItem(InterventionRecord $record): CalendarFeedItem
  {
    /** @var DateTimeImmutable $startsAt plannedStartAt or dueAt is guaranteed non-null by the WHERE clause */
    $startsAt = $record->plannedStartAt ?? $record->dueAt;
    $endsAt = null !== $record->dueAt && $record->dueAt->getTimestamp() !== $startsAt->getTimestamp() ? $record->dueAt : null;

    return new CalendarFeedItem(
      sourceKey: self::SOURCE_KEY,
      id: $record->id,
      title: $record->name,
      description: $record->description,
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: false,
      facilityId: $record->siteId,
      status: $record->status,
      targetType: self::TARGET_TYPE,
      targetId: $record->id,
    );
  }
  // #endregion
}
