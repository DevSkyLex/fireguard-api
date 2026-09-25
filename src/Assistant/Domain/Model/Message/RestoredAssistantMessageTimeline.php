<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use DateTimeImmutable;

/** Persisted timestamps of an assistant message. */
final readonly class RestoredAssistantMessageTimeline
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $completedAt,
  ) {
  }
}
