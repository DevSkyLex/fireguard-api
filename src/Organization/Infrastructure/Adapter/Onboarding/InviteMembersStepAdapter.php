<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Onboarding;

use Onboarding\Application\Contract\Step\{OrganizationOnboardingStepContext, OrganizationOnboardingStepDefinition};
use Onboarding\Application\Port\Inbound\OrganizationOnboardingStepContributionPort;

/**
 * Class InviteMembersStepAdapter
 *
 * Publishes Organization's onboarding action and checks its own authoritative availability.
 *
 * @category Adapter
 */
final readonly class InviteMembersStepAdapter implements OrganizationOnboardingStepContributionPort
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
      key: 'invite_members',
      label: 'Invite members',
      order: 30,
      required: false,
      actionMethod: 'POST',
      actionPath: '/api/organizations/{organizationId}/invitations',
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
