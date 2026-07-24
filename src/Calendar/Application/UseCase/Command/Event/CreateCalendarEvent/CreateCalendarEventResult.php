<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\CreateCalendarEvent;

use Calendar\Domain\Model\Event\CalendarEvent;
use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateCalendarEventResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateCalendarEventResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the event identifier
   * @param string $organizationId the owning organization identifier
   * @param string $title the event title
   * @param ?string $description the free-form description
   * @param DateTimeImmutable $startsAt the event start
   * @param ?DateTimeImmutable $endsAt the event end, when any
   * @param bool $allDay whether the event spans whole day(s)
   * @param ?string $facilityId the associated facility identifier, when any
   * @param string $createdByMemberId the creating organization member identifier
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $title,
    public ?string $description,
    public DateTimeImmutable $startsAt,
    public ?DateTimeImmutable $endsAt,
    public bool $allDay,
    public ?string $facilityId,
    public string $createdByMemberId,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param CalendarEvent $event the calendar event aggregate
   *
   * @return self the mapped result
   */
  public static function fromDomain(CalendarEvent $event): self
  {
    return new self(
      id: (string) $event->id(),
      organizationId: $event->organizationId(),
      title: $event->title(),
      description: $event->description(),
      startsAt: $event->startsAt(),
      endsAt: $event->endsAt(),
      allDay: $event->allDay(),
      facilityId: $event->facilityId(),
      createdByMemberId: $event->createdByMemberId(),
      createdAt: $event->createdAt(),
      updatedAt: $event->updatedAt(),
    );
  }
  // #endregion
}
