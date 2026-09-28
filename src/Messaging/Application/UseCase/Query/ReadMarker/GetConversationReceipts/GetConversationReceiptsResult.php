<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts;

use Messaging\Application\Contract\ReadMarker\ConversationReceiptPosition;
use Shared\Application\Message\ResultMessage;

/** Current participant positions in one conversation. */
final readonly class GetConversationReceiptsResult implements ResultMessage
{
  /**
   * @param list<ConversationReceiptPosition> $positions
   */
  public function __construct(public array $positions)
  {
  }
}
