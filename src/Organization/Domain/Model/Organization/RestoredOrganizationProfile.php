<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use Organization\Domain\ValueObject\{OrganizationSettings, PlanId};

/** Optional persisted presentation, preferences, and plan assignment. */
final readonly class RestoredOrganizationProfile
{
  public function __construct(
    public ?string $description = null,
    public ?string $logoUrl = null,
    public ?OrganizationSettings $settings = null,
    public ?PlanId $planId = null,
  ) {
  }
}
