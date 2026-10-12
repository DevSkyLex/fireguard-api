<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Onboarding;

use Facility\Application\Port\Inbound\FacilityAvailabilityPort;
use Onboarding\Application\Contract\Step\{OrganizationOnboardingStepContext, OrganizationOnboardingStepDefinition};
use Onboarding\Application\Port\Inbound\OrganizationOnboardingStepContributionPort;

/**
 * Class CreateFirstFacilityStepAdapter
 *
 * Publishes Facility's onboarding action and checks its own authoritative availability.
 *
 * @category Adapter
 */
final readonly class CreateFirstFacilityStepAdapter implements OrganizationOnboardingStepContributionPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param FacilityAvailabilityPort $availability owner availability capability
   *
   * @return void
   */
  public function __construct(private FacilityAvailabilityPort $availability)
  {
  }
  // #endregion

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
      key: 'create_first_facility',
      label: 'Create first facility',
      order: 40,
      required: true,
      actionMethod: 'POST',
      actionPath: '/api/organizations/{organizationId}/facilities',
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
    return null !== $context->organizationId
      && $this->availability->hasFacility($context->organizationId);
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
