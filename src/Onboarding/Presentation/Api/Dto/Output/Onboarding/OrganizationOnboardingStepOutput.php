<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Output\Onboarding;

use ApiPlatform\Metadata\ApiProperty;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationOnboardingStepOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingStepOutput
{
  // #region Properties
  /**
   * Property key.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $key = '';

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';

  /**
   * Property status.
   *
   * Possible values: pending, completed, blocked, skipped.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $status = 'pending';

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $required = false;

  /**
   * Property available.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $available = false;

  /**
   * Property reason.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $reason = null;

  /**
   * Property actionMethod.
   *
   * HTTP method to execute this step.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $actionMethod = null;

  /**
   * Property actionPath.
   *
   * API path to execute this step.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $actionPath = null;

  /**
   * Property rollbackAvailable.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $rollbackAvailable = false;

  /**
   * Property rollbackMethod.
   *
   * HTTP method to rollback this step when available.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $rollbackMethod = null;

  /**
   * Property rollbackPath.
   *
   * API path to rollback this step when available.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $rollbackPath = null;

  /**
   * Property skippable.
   *
   * Whether this step can be voluntarily skipped.
   *
   * @since 2.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $skippable = false;

  /**
   * Property skipAvailable.
   *
   * Whether the skip action is currently available for this step.
   *
   * @since 2.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $skipAvailable = false;

  /**
   * Property skipMethod.
   *
   * HTTP method to skip this step.
   *
   * @since 2.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $skipMethod = null;

  /**
   * Property skipPath.
   *
   * API path to skip this step.
   *
   * @since 2.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $skipPath = null;

  /**
   * Property completedAt.
   *
   * ISO 8601 timestamp when the step was last completed or skipped, if available.
   *
   * @since 2.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $completedAt = null;
  // #endregion
}
