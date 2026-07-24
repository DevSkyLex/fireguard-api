<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteCalendarEventResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCalendarEventResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $eventId the deleted event identifier
   * @param string $organizationId the owning organization identifier
   */
  public function __construct(
    public string $eventId,
    public string $organizationId,
  ) {
  }
  // #endregion
}
