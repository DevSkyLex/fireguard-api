<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Organization;

use DateTimeImmutable;

/** The organization facts needed to choose an onboarding target safely. */
final readonly class OnboardingOrganizationCandidate
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Projects the organization facts used to select an onboarding target.
   *
   * @access public
   *
   * @param string $id organization identifier
   * @param string $name organization display name
   * @param string $ownerUserId user who owns the organization
   * @param string $createdByUserId user who created the organization
   * @param bool $isActive whether the organization is active
   * @param DateTimeImmutable $createdAt organization creation timestamp
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $name,
    public string $ownerUserId,
    public string $createdByUserId,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
