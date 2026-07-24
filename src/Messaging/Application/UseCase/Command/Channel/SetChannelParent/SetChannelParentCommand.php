<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\SetChannelParent;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetChannelParentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetChannelParentCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the channel (conversation) id value
   * @param ?string $parentConversationId the parent channel id to nest under, or null to detach
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public ?string $parentConversationId,
  ) {
  }
}
