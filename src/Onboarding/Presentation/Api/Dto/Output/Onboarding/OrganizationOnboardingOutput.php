<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Dto\Output\Onboarding;

use ApiPlatform\Metadata\ApiProperty;
use Onboarding\Presentation\Api\Serialization\OnboardingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationOnboardingOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingOutput
{
  // #region Properties
  /**
   * Property flow.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $flow = 'organization';

  /**
   * Property state.
   *
   * Possible values: in_progress, completed, blocked.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $state = 'in_progress';

  /**
   * Property nextStep.
   *
   * Possible values: create_organization, invite_members, create_first_facility, create_first_equipment, run_first_inspection.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $nextStep = null;

  /**
   * Property blockedReason.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $blockedReason = null;

  /**
   * Property completedSteps.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $completedSteps = [];

  /**
   * Property skippedSteps.
   *
   * @since 2.0.0
   *
   * @var list<string>
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $skippedSteps = [];

  /**
   * Property steps.
   *
   * @since 1.0.0
   *
   * @var list<OrganizationOnboardingStepOutput>
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $steps = [];

  /**
   * Property stepHistory.
   *
   * Ordered audit log of step completions and voluntary skips.
   *
   * @since 2.0.0
   *
   * @var list<OnboardingStepHistoryEntryOutput>
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $stepHistory = [];

  /**
   * Property targetOrganizationId.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $targetOrganizationId = null;

  /**
   * Property targetOrganizationName.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $targetOrganizationName = null;

  /**
   * Property canRollback.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $canRollback = false;

  /**
   * Property lastRollbackableStep.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $lastRollbackableStep = null;

  /**
   * Property rollbackMethod.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $rollbackMethod = null;

  /**
   * Property rollbackPath.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $rollbackPath = null;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $updatedAt = null;

  /**
   * Property dismissed.
   *
   * Whether the user voluntarily hid the non-blocking activation flow. Onboarding
   * never blocks navigation; this flag lets the shell hide the setup checklist.
   *
   * @since 3.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $dismissed = false;

  /**
   * Property dismissedAt.
   *
   * @since 3.0.0
   */
  #[Groups([OnboardingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, required: false)]
  public ?string $dismissedAt = null;
  // #endregion
}
