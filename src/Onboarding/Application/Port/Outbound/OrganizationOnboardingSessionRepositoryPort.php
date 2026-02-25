<?php

declare(strict_types=1);

namespace Onboarding\Application\Port\Outbound;

use Onboarding\Domain\Model\OrganizationOnboardingSession\OrganizationOnboardingSession;

/**
 * Port OrganizationOnboardingSessionRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationOnboardingSessionRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   */
  public function save(OrganizationOnboardingSession $session): void;

  /**
   * Method findByUserId.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   *
   * @return ?OrganizationOnboardingSession the onboarding session when found
   */
  public function findByUserId(string $userId): ?OrganizationOnboardingSession;

  /**
   * Method deleteByUserId.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   */
  public function deleteByUserId(string $userId): void;
  // #endregion
}
