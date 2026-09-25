<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

/** Persisted identifiers and flow selection of an onboarding session. */
final readonly class RestoredOnboardingIdentity
{
  public function __construct(
    public string $id,
    public string $userId,
    public string $flow,
  ) {
  }
}
