<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\ListChannelParticipants;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListChannelParticipantsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListChannelParticipantsQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the channel (conversation) id value
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
  ) {
  }
}
