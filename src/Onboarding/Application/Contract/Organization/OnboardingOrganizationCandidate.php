<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Organization;

use DateTimeImmutable;

/** The organization facts needed to choose an onboarding target safely. */
final readonly class OnboardingOrganizationCandidate
{
  public function __construct(
    public string $id,
    public string $name,
    public string $ownerUserId,
    public string $createdByUserId,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
