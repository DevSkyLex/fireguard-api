<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Operation;

/**
 * Operation CalendarOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarOperations
{
  public const string CREATE_CALENDAR_EVENT = 'createCalendarEvent';

  public const string GET_CALENDAR_EVENT = 'getCalendarEvent';

  public const string UPDATE_CALENDAR_EVENT = 'updateCalendarEvent';

  public const string DELETE_CALENDAR_EVENT = 'deleteCalendarEvent';

  public const string GET_CALENDAR_FEED = 'getCalendarFeed';
}
