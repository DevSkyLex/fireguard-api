<?php

declare(strict_types=1);

namespace User\Domain\Model\EmailChange;

use DateTimeImmutable;

/** Persisted lifecycle timestamps of an email change request. */
final readonly class RestoredEmailChangeTimeline
{
  public function __construct(
    public DateTimeImmutable $requestedAt,
    public DateTimeImmutable $expiresAt,
    public ?DateTimeImmutable $confirmedAt,
  ) {
  }
}
