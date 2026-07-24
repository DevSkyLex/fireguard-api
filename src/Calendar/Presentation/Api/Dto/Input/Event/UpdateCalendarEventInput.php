<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Input\Event;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use DateTimeInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateCalendarEventInput.
 *
 * A PATCH-style partial update. Field presence is read from the merge-patch
 * request: omitted properties stay unchanged, while an explicit null clears a
 * nullable description, end date or facility.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateCalendarEventInput
{
  // #region Properties
  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 1, max: 255)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Event title', required: false)]
  public ?string $title = null;

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
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Event start, ISO 8601 with an explicit timezone offset', required: false)]
  public ?string $startsAt = null;

  /**
   * Property endsAt.
   *
   * @since 1.0.0
   */
  #[Assert\DateTime(format: DateTimeInterface::ATOM)]
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Event end, ISO 8601 with an explicit timezone offset', required: false)]
  public ?string $endsAt = null;

  /**
   * Property allDay.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether the event spans whole day(s)', required: false)]
  public ?bool $allDay = null;

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
