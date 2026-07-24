<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\UpdateChannel;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateChannelCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateChannelCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the channel (conversation) id value
   * @param ?string $name the new channel display name, if renaming
   * @param ?bool $isArchived the requested archived flag, if archiving/unarchiving
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public ?string $name = null,
    public ?bool $isArchived = null,
  ) {
  }
}
