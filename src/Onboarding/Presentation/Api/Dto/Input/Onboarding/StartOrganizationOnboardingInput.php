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
   * @since 1.1.0 Explicit user intent; absent preserves legacy resume.
   */
  #[Groups([OnboardingSerializationGroup::WRITE])]
  #[\Symfony\Component\Validator\Constraints\Choice(choices: ['create'])]
  public ?string $intent = null;

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
