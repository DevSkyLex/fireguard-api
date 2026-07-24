<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input\Channel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateChannelInput.
 *
 * `PATCH /api/channels/{id}` request body: rename and/or archive/unarchive.
 * Both fields are optional; only the ones present are applied.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateChannelInput
{
  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\Length(min: 2, max: 80)]
  public ?string $name = null;

  /**
   * Property isArchived.
   *
   * @since 1.0.0
   */
  public ?bool $isArchived = null;
}
