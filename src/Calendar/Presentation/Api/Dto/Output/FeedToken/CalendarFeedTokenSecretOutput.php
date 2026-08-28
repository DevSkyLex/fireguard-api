<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Output\FeedToken;

use ApiPlatform\Metadata\ApiProperty;
use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO CalendarFeedTokenSecretOutput.
 *
 * The single response that ever carries the raw feed secret: returned once
 * by the 201 of the create/rotate endpoint, then gone — the backend only
 * keeps its SHA-256 hash.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarFeedTokenSecretOutput
{
  // #region Properties
  /**
   * Property secret.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $secret = '';

  /**
   * Property feedUrl.
   *
   * The complete, subscribable `.ics` URL embedding the secret.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $feedUrl = '';

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $createdAt = '';

  /**
   * Property rotated.
   *
   * Whether this creation revoked a previously active token.
   *
   * @since 1.0.0
   */
  #[Groups([CalendarSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $rotated = false;
  // #endregion
}
