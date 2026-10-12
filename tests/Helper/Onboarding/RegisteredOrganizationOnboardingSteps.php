<?php

declare(strict_types=1);

namespace Tests\Helper\Onboarding;

use Equipment\Application\Port\Inbound\EquipmentAvailabilityPort;
use Equipment\Infrastructure\Adapter\Onboarding\CreateFirstEquipmentStepAdapter;
use Facility\Application\Port\Inbound\FacilityAvailabilityPort;
use Facility\Infrastructure\Adapter\Onboarding\CreateFirstFacilityStepAdapter;
use Onboarding\Application\Contract\Step\OrganizationOnboardingStepDefinition;
use Onboarding\Application\Service\OrganizationOnboardingStepRegistry;
use Organization\Infrastructure\Adapter\Onboarding\{CreateOrganizationStepAdapter, InviteMembersStepAdapter, SelectPlanStepAdapter};

use function array_map;

/**
 * Class RegisteredOrganizationOnboardingSteps
 *
 * Composes the production owner contributions for compatibility tests without a container.
 *
 * @category TestHelper
 */
final class RegisteredOrganizationOnboardingSteps
{
  // #region Methods
  /**
   * Method withAvailability
   *
   * @access public
   *
   * @param FacilityAvailabilityPort $facilities facility availability test capability
   * @param EquipmentAvailabilityPort $equipment equipment availability test capability
   *
   * @return OrganizationOnboardingStepRegistry production contributor composition
   */
  public static function withAvailability(FacilityAvailabilityPort $facilities, EquipmentAvailabilityPort $equipment): OrganizationOnboardingStepRegistry
  {
    return new OrganizationOnboardingStepRegistry([
      new CreateOrganizationStepAdapter(),
      new SelectPlanStepAdapter(),
      new InviteMembersStepAdapter(),
      new CreateFirstFacilityStepAdapter($facilities),
      new CreateFirstEquipmentStepAdapter($equipment),
    ]);
  }

  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<OrganizationOnboardingStepDefinition> unchanged public metadata
   */
  public static function definitions(): array
  {
    return [
      CreateOrganizationStepAdapter::definition(),
      SelectPlanStepAdapter::definition(),
      InviteMembersStepAdapter::definition(),
      CreateFirstFacilityStepAdapter::definition(),
      CreateFirstEquipmentStepAdapter::definition(),
    ];
  }

  /**
   * Method keys
   *
   * @access public
   *
   * @return list<string> unchanged canonical keys
   */
  public static function keys(): array
  {
    return array_map(static fn (OrganizationOnboardingStepDefinition $step): string => $step->key, self::definitions());
  }
  // #endregion
}
