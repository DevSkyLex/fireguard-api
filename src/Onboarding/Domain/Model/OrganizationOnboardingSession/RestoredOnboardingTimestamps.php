<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

use DateTimeImmutable;

/** Persisted session timestamps, including optional dismissal. */
final readonly class RestoredOnboardingTimestamps
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $dismissedAt = null,
  ) {
  }
}
