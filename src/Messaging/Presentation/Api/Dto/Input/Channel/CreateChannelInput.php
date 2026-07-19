<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input\Channel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateChannelInput.
 *
 * `POST /api/channels` request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateChannelInput
{
  /**
   * Property organization.
   *
   * The owning organization IRI.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Organization is required.')]
  public string $organization = '';

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Channel name is required.')]
  #[Assert\Length(min: 2, max: 80)]
  public string $name = '';
}
