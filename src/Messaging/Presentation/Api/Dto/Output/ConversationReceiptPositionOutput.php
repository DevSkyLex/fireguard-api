<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

/** A current participant's last confirmed delivery and read message positions. */
final class ConversationReceiptPositionOutput
{
  public string $memberId = '';

  public ?string $deliveredMessageId = null;

  public ?string $deliveredThroughAt = null;

  public ?string $readMessageId = null;

  public ?string $readThroughAt = null;
}
