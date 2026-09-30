<?php

declare(strict_types=1);

namespace Organization\Domain\Model\Organization;

use Organization\Domain\ValueObject\{OrganizationSettings, PlanId};

/** Optional persisted presentation, preferences, and plan assignment. */
final readonly class RestoredOrganizationProfile
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries optional descriptive profile and plan fields restored for an organization.
   *
   * @access public
   *
   * @param ?string $description optional organization description
   * @param ?string $logoUrl optional organization logo URL
   * @param ?OrganizationSettings $settings optional organization settings
   * @param ?PlanId $planId optional assigned plan identifier
   *
   * @return void
   */
  public function __construct(
    public ?string $description = null,
    public ?string $logoUrl = null,
    public ?OrganizationSettings $settings = null,
    public ?PlanId $planId = null,
  ) {
  }
  // #endregion
}
