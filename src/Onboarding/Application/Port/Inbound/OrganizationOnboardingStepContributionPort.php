<?php

declare(strict_types=1);

namespace Onboarding\Application\Port\Inbound;

use Onboarding\Application\Contract\Step\{OrganizationOnboardingStepContext, OrganizationOnboardingStepDefinition};

/**
 * Interface OrganizationOnboardingStepContributionPort
 *
 * Owners publish a pure manifest for boot validation and evaluate/confirm their own action.
 * Execution follows a successful evaluation and remains within the session transaction.
 *
 * @category Port
 */
interface OrganizationOnboardingStepContributionPort
{
  // #region Methods
  /**
   * Method definition
   *
   * @access public
   *
   * @return OrganizationOnboardingStepDefinition immutable manifest, independent of service dependencies
   */
  public static function definition(): OrganizationOnboardingStepDefinition;

  /**
   * Method evaluate
   *
   * @access public
   *
   * @param OrganizationOnboardingStepContext $context authenticated and pinned flow context
   *
   * @return bool whether the owning module's precondition is satisfied
   */
  public function evaluate(OrganizationOnboardingStepContext $context): bool;

  /**
   * Method execute
   *
   * @access public
   *
   * @param OrganizationOnboardingStepContext $context authenticated and pinned flow context
   *
   * @return void
   */
  public function execute(OrganizationOnboardingStepContext $context): void;
  // #endregion
}
