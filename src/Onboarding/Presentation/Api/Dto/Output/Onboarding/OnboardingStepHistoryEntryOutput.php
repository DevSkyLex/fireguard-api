<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Output\Onboarding;

use ApiPlatform\Metadata\ApiProperty;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OnboardingStepHistoryEntryOutput.
 *
 * Represents a single entry in the step audit log exposed via the onboarding
 * output. Each entry records when a step was completed or voluntarily skipped.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OnboardingStepHistoryEntryOutput
{
  // #region Properties
  /**
   * Property stepKey.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $stepKey = '';

  /**
   * Property occurredAt.
   *
   * ISO 8601 timestamp of when the event occurred.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $occurredAt = '';

  /**
   * Property skipped.
   *
   * True when the step was voluntarily skipped rather than executed.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $skipped = false;
  // #endregion
}
