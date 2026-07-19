<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeleteCalendarEventCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCalendarEventCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting user identifier
   * @param string $eventId the calendar event identifier
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $eventId,
  ) {
  }
  // #endregion
}
