<?php

declare(strict_types=1);

namespace Onboarding\Domain\Model\OrganizationOnboardingSession;

/** Persisted flow status and target selected by the user. */
final readonly class RestoredOnboardingStatus
{
  public function __construct(
    public string $state,
    public ?string $nextStep,
    public ?string $blockedReason,
    public ?string $targetOrganizationId,
    public ?string $targetOrganizationName,
    public bool $creationIntent = false,
  ) {
  }
}
