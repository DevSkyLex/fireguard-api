<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\ReadMarker;

use DateTimeImmutable;

/** A participant's confirmed delivery and read positions in one conversation. */
final readonly class ConversationReceiptPosition
{
  public function __construct(
    public string $memberId,
    public ?string $deliveredMessageId,
    public ?DateTimeImmutable $deliveredThroughAt,
    public ?string $readMessageId,
    public ?DateTimeImmutable $readThroughAt,
  ) {
  }
}
