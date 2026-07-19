<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input\Channel;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO SetChannelParentInput.
 *
 * `PATCH /api/channels/{id}/parent` request body (L2.6). `parentChannelId:
 * null` detaches the channel (it becomes/stays top-level); a non-null value
 * nests it under that channel, subject to
 * `MessagingChannelHierarchyGuard`'s cycle/cross-organization/max-depth
 * checks. Mirrors `BindChannelTeamInput`'s `teamId` tri-state convention
 * (raw id in, IRI out via `ChannelOutput::$parent`).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetChannelParentInput
{
  /**
   * Property parentChannelId.
   *
   * @since 1.0.0
   */
  #[Assert\Uuid(message: 'Parent channel ID must be a valid UUID.')]
  public ?string $parentChannelId = null;
}
