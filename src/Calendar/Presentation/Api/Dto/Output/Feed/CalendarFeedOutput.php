<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Output\Feed;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO CalendarFeedOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedOutput
{
  // #region Properties
  /**
   * Property from.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $from = '';

  /**
   * Property to.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $to = '';

  /**
   * Property items.
   *
   * @since 1.0.0
   *
   * @var list<CalendarFeedItemOutput>
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $items = [];
  // #endregion
}
