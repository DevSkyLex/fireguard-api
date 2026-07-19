<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Output\Event;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO CalendarEventOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarEventOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

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
   * Property createdByMemberId.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdByMemberId = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $updatedAt = '';
  // #endregion
}
