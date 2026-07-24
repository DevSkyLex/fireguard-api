<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ChannelParticipantOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChannelParticipantOutput
{
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $memberId = '';

  /**
   * Property role.
   *
   * Free-form membership label, if any.
   *
   * @since 1.0.0
   */
  public ?string $role = null;

  /**
   * Property source.
   *
   * `manual` or `team`.
   *
   * @since 1.0.0
   */
  public string $source = '';

  /**
   * Property addedAt.
   *
   * @since 1.0.0
   */
  public string $addedAt = '';
}
