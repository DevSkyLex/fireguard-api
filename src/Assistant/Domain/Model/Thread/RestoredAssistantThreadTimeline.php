<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Thread;

use DateTimeImmutable;

/** Persisted timestamps of a member-private assistant thread. */
final readonly class RestoredAssistantThreadTimeline
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $lastMessageAt,
  ) {
  }
}
