<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase RemoveChannelParticipantCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveChannelParticipantCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the channel (conversation) id value
   * @param string $memberId the organization member identifier to remove
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public string $memberId,
  ) {
  }
}
