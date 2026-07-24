<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\CreateCalendarEvent;

use DateTimeImmutable;
use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateCalendarEventCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateCalendarEventCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the acting user identifier
   * @param string $title the event title
   * @param ?string $description the free-form description
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $title,
    public ?string $description,
    public DateTimeImmutable $startsAt,
    public ?DateTimeImmutable $endsAt,
    public bool $allDay,
    public ?string $facilityId,
  ) {
  }
  // #endregion
}
