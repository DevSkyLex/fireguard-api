<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use DateTimeImmutable;

/** Persisted generation attempt and its originating question. */
final readonly class RestoredAssistantMessageAttempt
{
  public function __construct(
    public ?string $attemptId = null,
    public int $attemptNumber = 0,
    public int $attemptSequence = 0,
    public ?DateTimeImmutable $attemptExpiresAt = null,
    public ?string $questionMessageId = null,
    public ?float $temperature = null,
  ) {
  }
}
