<?php

declare(strict_types=1);

namespace Calendar\Application\Port\Outbound\Event;

use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;

/**
 * Port CalendarEventRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface CalendarEventRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a calendar event aggregate.
   *
   * @since 1.0.0
   *
   * @param CalendarEvent $event the calendar event aggregate
   */
  public function save(CalendarEvent $event): void;

  /**
   * Method remove.
   *
   * Removes a calendar event aggregate.
   *
   * @since 1.0.0
   *
   * @param CalendarEvent $event the calendar event aggregate
   */
  public function remove(CalendarEvent $event): void;

  /**
   * Method findById.
   *
   * Finds a calendar event by identifier.
   *
   * @since 1.0.0
   *
   * @param CalendarEventId $id the calendar event identifier
   *
   * @return ?CalendarEvent the calendar event aggregate when found
   */
  public function findById(CalendarEventId $id): ?CalendarEvent;

  /**
   * Method listBetween.
   *
   * Lists standalone events for an organization that overlap the given
   * inclusive date range (an event overlaps when `startsAt <= $to` and
   * `(endsAt ?? startsAt) >= $from`), ordered by `startsAt` ascending then
   * `id` ascending. Implementations must never load a whole organization's
   * history: `$limit` is a hard cap pushed down into the query, not a
   * post-fetch truncation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param DateTimeImmutable $from the inclusive range lower bound
   * @param DateTimeImmutable $to the inclusive range upper bound
   * @param int $limit the maximum number of events to return
   *
   * @return list<CalendarEvent> the overlapping events
   */
  public function listBetween(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array;
  // #endregion
}
