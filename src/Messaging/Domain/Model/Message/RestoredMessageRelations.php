<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use DateTimeImmutable;

/** Optional pin and thread relationship stored with the message. */
final readonly class RestoredMessageRelations
{
  public function __construct(
    public ?DateTimeImmutable $pinnedAt = null,
    public ?string $pinnedByMemberId = null,
    public ?string $parentMessageId = null,
    public int $replyCount = 0,
  ) {
  }
}
