<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input\Channel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO AddChannelParticipantInput.
 *
 * `POST /api/channels/{id}/participants` request body.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AddChannelParticipantInput
{
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Member ID is required.')]
  #[Assert\Uuid(message: 'Member ID must be a valid UUID.')]
  public string $memberId = '';

  /**
   * Property role.
   *
   * Free-form membership label (e.g. "lead"). NOT an RBAC role.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 50)]
  public ?string $role = null;
}
