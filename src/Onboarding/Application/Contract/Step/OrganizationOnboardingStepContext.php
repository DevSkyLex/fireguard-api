<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Step;

/**
 * Class OrganizationOnboardingStepContext
 *
 * Carries the authenticated session and pinned organization to the contributing owner.
 *
 * @category Contract
 */
final readonly class OrganizationOnboardingStepContext
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $sessionId persisted onboarding session
   * @param string $userId authenticated actor
   * @param string|null $organizationId independently validated pinned organization
   *
   * @return void
   */
  public function __construct(
    public string $sessionId,
    public string $userId,
    public ?string $organizationId,
  ) {
  }
  // #endregion
}
