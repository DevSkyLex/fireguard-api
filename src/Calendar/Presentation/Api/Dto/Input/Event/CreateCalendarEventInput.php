<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Input\Event;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateCalendarEventInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateCalendarEventInput
{
  // #region Properties
  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'A title is required.')]
  #[Assert\Length(max: 255)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Event title', required: true, example: 'Fire drill')]
  public string $title = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 5000)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Free-form description', required: false)]
  public ?string $description = null;

  /**
   * Property startsAt.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'A start date/time is required.')]
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Event start, ISO 8601 with an explicit timezone offset', required: true, example: '2026-08-01T09:00:00+02:00')]
  public string $startsAt = '';

  /**
   * Property endsAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Event end, ISO 8601 with an explicit timezone offset', required: false, example: '2026-08-01T11:00:00+02:00')]
  public ?string $endsAt = null;

  /**
   * Property allDay.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the event spans whole day(s)', required: false)]
  public bool $allDay = false;

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Associated facility identifier', required: false)]
  public ?string $facilityId = null;
  // #endregion
}
