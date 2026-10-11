<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use Organization\Application\Contract\Onboarding\OnboardingOrganization;

/**
 * Interface OrganizationOnboardingDirectoryPort
 *
 * Provides scoped onboarding facts and delegates archive to the canonical organization command.
 *
 * @category Port
 */
interface OrganizationOnboardingDirectoryPort
{
  // #region Methods
  /**
   * Method organizationsForUser
   *
   * @access public
   *
   * @param string $userId current member identity
   *
   * @return list<OnboardingOrganization> the same ordered membership selection as the organization list
   */
  public function organizationsForUser(string $userId): array;

  /**
   * Method archive
   *
   * The caller must independently establish its onboarding ownership and creation-time invariants.
   *
   * @access public
   *
   * @param string $organizationId organization to archive
   * @param string $slugConfirmation current archive confirmation
   *
   * @return void
   */
  public function archive(string $organizationId, string $slugConfirmation): void;
  // #endregion
}
