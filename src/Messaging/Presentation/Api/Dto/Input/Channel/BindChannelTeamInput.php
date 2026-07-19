<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input\Channel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO BindChannelTeamInput.
 *
 * `PATCH /api/channels/{id}/team` request body. `teamId: null` unbinds the
 * channel (the current participant snapshot is retained as manual
 * participants).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BindChannelTeamInput
{
  /**
   * Property teamId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Team ID must be a valid UUID.')]
  public ?string $teamId = null;
}
