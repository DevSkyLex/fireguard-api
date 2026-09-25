<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Conversation;

use DateTimeImmutable;
use Messaging\Domain\ValueObject\ConversationVisibility;

/** Persisted visibility, counters and lifecycle timestamps of a conversation. */
final readonly class RestoredConversationActivity
{
  public function __construct(
    public ConversationVisibility $visibility,
    public ?DateTimeImmutable $lastMessageAt,
    public int $messagesCount,
    public bool $isArchived,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
