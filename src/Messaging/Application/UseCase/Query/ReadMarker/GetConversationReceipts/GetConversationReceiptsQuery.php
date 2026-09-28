<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts;

use Shared\Application\Message\QueryMessage;

/** Requests the current participants' receipt positions. */
final readonly class GetConversationReceiptsQuery implements QueryMessage
{
  public function __construct(public string $userId, public string $conversationId)
  {
  }
}
