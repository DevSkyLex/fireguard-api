<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Channel\GetChannel;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetChannelQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChannelQuery implements QueryMessage
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
