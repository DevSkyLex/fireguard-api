<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Operation;

/**
 * Operation CalendarOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarOperations
{
  /**
   * Constant CREATE_CALENDAR_EVENT
   */
  public const string CREATE_CALENDAR_EVENT = 'createCalendarEvent';

  /**
   * Constant GET_CALENDAR_EVENT
   */
  public const string GET_CALENDAR_EVENT = 'getCalendarEvent';

  /**
   * Constant UPDATE_CALENDAR_EVENT
   */
  public const string UPDATE_CALENDAR_EVENT = 'updateCalendarEvent';

  /**
   * Constant DELETE_CALENDAR_EVENT
   */
  public const string DELETE_CALENDAR_EVENT = 'deleteCalendarEvent';

  /**
   * Constant GET_CALENDAR_FEED
   */
  public const string GET_CALENDAR_FEED = 'getCalendarFeed';

  /**
   * Constant CREATE_CALENDAR_FEED_TOKEN
   */
  public const string CREATE_CALENDAR_FEED_TOKEN = 'createCalendarFeedToken';

  /**
   * Constant GET_CALENDAR_FEED_TOKEN
   */
  public const string GET_CALENDAR_FEED_TOKEN = 'getCalendarFeedToken';

  /**
   * Constant DELETE_CALENDAR_FEED_TOKEN
   */
  public const string DELETE_CALENDAR_FEED_TOKEN = 'deleteCalendarFeedToken';

  /**
   * Constant GET_CALENDAR_FEED_ICS
   */
  public const string GET_CALENDAR_FEED_ICS = 'getCalendarFeedIcs';
}
