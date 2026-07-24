<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\Event\GetCalendarEvent;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetCalendarEventQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarEventQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $organizationId the owning organization identifier
   * @param string $eventId the calendar event identifier
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public string $eventId,
  ) {
  }
  // #endregion
}
