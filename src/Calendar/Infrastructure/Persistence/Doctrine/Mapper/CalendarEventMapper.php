<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Persistence\Doctrine\Mapper;

use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use Calendar\Infrastructure\Persistence\Doctrine\Record\CalendarEventRecord;

/**
 * Mapper CalendarEventMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarEventMapper
{
  // #region Methods
  /**
   * Method toDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param CalendarEventRecord $record the persistence record
   *
   * @return CalendarEvent the domain aggregate
   */
  public static function toDomain(CalendarEventRecord $record): CalendarEvent
  {
    return CalendarEvent::reconstitute(
      id: CalendarEventId::fromString($record->id),
      organizationId: $record->organizationId,
      title: $record->title,
      description: $record->description,
      startsAt: $record->startsAt,
      endsAt: $record->endsAt,
      allDay: $record->allDay,
      facilityId: $record->facilityId,
      createdByMemberId: $record->createdByMemberId,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
    );
  }

  /**
   * Method toRecord.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param CalendarEvent $event the domain aggregate
   * @param CalendarEventRecord $record the persistence record to populate
   */
  public static function toRecord(CalendarEvent $event, CalendarEventRecord $record): void
  {
    $record->id = (string) $event->id();
    $record->organizationId = $event->organizationId();
    $record->title = $event->title();
    $record->description = $event->description();
    $record->startsAt = $event->startsAt();
    $record->endsAt = $event->endsAt();
    $record->allDay = $event->allDay();
    $record->facilityId = $event->facilityId();
    $record->createdByMemberId = $event->createdByMemberId();
    $record->createdAt = $event->createdAt();
    $record->updatedAt = $event->updatedAt();
  }
  // #endregion
}
