<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

/** Positions are scoped to the conversation's current participants. */
final class ConversationReceiptsOutput
{
  /**
   * @var list<ConversationReceiptPositionOutput>
   */
  public array $receipts = [];
}
