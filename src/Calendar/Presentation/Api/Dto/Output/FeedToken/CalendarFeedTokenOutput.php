<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Output\FeedToken;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO CalendarFeedTokenOutput.
 *
 * Feed token metadata — deliberately without the secret or its hash.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenOutput
{
  // #region Properties
  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property lastUsedAt.
   *
   * The last recorded feed fetch (persisted at most once per hour), when any.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastUsedAt = null;
  // #endregion
}
