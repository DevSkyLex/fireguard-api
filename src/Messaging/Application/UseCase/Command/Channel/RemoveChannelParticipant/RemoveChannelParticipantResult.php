<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase RemoveChannelParticipantResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveChannelParticipantResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   * @param string $memberId the removed member's identifier
   */
  public function __construct(
    public string $conversationId,
    public string $memberId,
  ) {
  }
}
