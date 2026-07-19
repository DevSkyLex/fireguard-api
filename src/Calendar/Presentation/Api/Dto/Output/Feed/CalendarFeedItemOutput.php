<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Output\Feed;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO CalendarFeedItemOutput.
 *
 * One entry of the unified calendar feed. `sourceKey` is one of
 * `calendar_event`, `inspection`, `intervention`, or `maintenance` — the
 * mockup's fourth category, "Audit", has no business existence in this
 * backend (see `Calendar\MODULE.md`). `status` is always the source's raw
 * enum value, never a translated label.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedItemOutput
{
  // #region Properties
  /**
   * Property sourceKey.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $sourceKey = '';

  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $id = '';

  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $title = '';

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $description = null;

  /**
   * Property startsAt.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $startsAt = '';

  /**
   * Property endsAt.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $endsAt = null;

  /**
   * Property allDay.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $allDay = false;

  /**
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $facilityId = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $status = null;

  /**
   * Property targetType.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $targetType = '';

  /**
   * Property targetId.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $targetId = '';
  // #endregion
}
