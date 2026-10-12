<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Onboarding;

use Onboarding\Application\Contract\Step\{OrganizationOnboardingStepContext, OrganizationOnboardingStepDefinition};
use Onboarding\Application\Port\Inbound\OrganizationOnboardingStepContributionPort;

/**
 * Class SelectPlanStepAdapter
 *
 * Publishes Organization's onboarding action and checks its own authoritative availability.
 *
 * @category Adapter
 */
final readonly class SelectPlanStepAdapter implements OrganizationOnboardingStepContributionPort
{
  // #region Methods
  /**
   * Method definition
   *
   * @access public
   *
   * @return OrganizationOnboardingStepDefinition stable descriptor in the existing FireGuard order
   */
  public static function definition(): OrganizationOnboardingStepDefinition
  {
    return new OrganizationOnboardingStepDefinition(
      key: 'select_plan',
      label: 'Choose a plan',
      order: 20,
      required: false,
      actionMethod: 'POST',
      actionPath: '/api/organizations/{organizationId}/billing/checkout',
    );
  }

  /**
   * Method evaluate
   *
   * @access public
   *
   * @param OrganizationOnboardingStepContext $context authenticated pinned session
   *
   * @return bool whether the action can be confirmed
   */
  public function evaluate(OrganizationOnboardingStepContext $context): bool
  {
    return null !== $context->organizationId;
  }

  /**
   * Method execute
   *
   * The action is performed through the owner's existing API; this confirms that external action.
   *
   * @access public
   *
   * @param OrganizationOnboardingStepContext $context authenticated pinned session
   *
   * @return void
   */
  public function execute(OrganizationOnboardingStepContext $context): void
  {
  }
  // #endregion
}
