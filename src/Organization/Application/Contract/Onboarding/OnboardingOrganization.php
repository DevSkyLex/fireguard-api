<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Onboarding;

use DateTimeImmutable;

/**
 * Class OnboardingOrganization
 *
 * Publishes membership and creation facts used for onboarding target selection and rollback.
 *
 * @category Contract
 */
final readonly class OnboardingOrganization
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $id organization identifier
   * @param string $name display name
   * @param string $slug archive confirmation value
   * @param string $ownerUserId owner identity
   * @param string $createdByUserId creator identity
   * @param bool $isActive current organization availability
   * @param DateTimeImmutable $createdAt original creation instant
   *
   * @return void
   */
  public function __construct(
    public string $id,
    public string $name,
    public string $slug,
    public string $ownerUserId,
    public string $createdByUserId,
    public bool $isActive,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
