<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Input\Onboarding;

use ApiPlatform\Metadata\ApiProperty;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO StartOrganizationOnboardingInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StartOrganizationOnboardingInput
{
  // #region Properties
  /**
   * Property reset.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'When true, resets the persisted onboarding session for the authenticated user.',
    required: false,
    example: false,
  )]
  public bool $reset = false;
  // #endregion
}
