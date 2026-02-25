<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Mapper\Onboarding;

use Onboarding\Application\Service\OrganizationOnboardingSessionState;
use Onboarding\Domain\ValueObject\OrganizationOnboardingStep;
use Onboarding\Presentation\Api\Dto\Output\Onboarding\{
  OnboardingStepHistoryEntryOutput,
  OrganizationOnboardingOutput,
  OrganizationOnboardingStepOutput
};

use function array_map;
use function in_array;
use function sprintf;

/**
 * Assembler OrganizationOnboardingOutputAssembler.
 *
 * Converts an Application-layer {@see OrganizationOnboardingSessionState}
 * into a Presentation-layer {@see OrganizationOnboardingOutput} DTO,
 * including step descriptors, rollback metadata, skip metadata, and step history.
 *
 * @category Assembler
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingOutputAssembler
{
  // #region Constants
  private const string ROLLBACK_PATH = '/api/onboarding/organization/rollback';

  private const string SKIP_PATH_TEMPLATE = '/api/onboarding/organization/steps/%s/skip';
  // #endregion

  // #region Methods
  /**
   * Method fromState.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSessionState $state the resolved flow state
   *
   * @return OrganizationOnboardingOutput the assembled output DTO
   */
  public static function fromState(OrganizationOnboardingSessionState $state): OrganizationOnboardingOutput
  {
    $output = new OrganizationOnboardingOutput();
    $output->flow = $state->flow;
    $output->state = $state->state;
    $output->nextStep = $state->nextStep;
    $output->blockedReason = $state->blockedReason;
    $output->targetOrganizationId = $state->targetOrganizationId;
    $output->targetOrganizationName = $state->targetOrganizationName;
    $output->completedSteps = $state->completedSteps;
    $output->skippedSteps = $state->skippedSteps;
    $output->updatedAt = $state->updatedAt;

    // Step history
    $output->stepHistory = array_map(
      static function (array $entry): OnboardingStepHistoryEntryOutput {
        $dto = new OnboardingStepHistoryEntryOutput();
        $dto->stepKey = $entry['stepKey'];
        $dto->occurredAt = $entry['occurredAt'];
        $dto->skipped = $entry['skipped'];

        return $dto;
      },
      $state->stepHistory,
    );

    // Resolve completedAt for each step from history (last matching entry)
    $completedAtByStep = [];
    foreach ($state->stepHistory as $entry) {
      $completedAtByStep[$entry['stepKey']] = $entry['occurredAt'];
    }

    $createOrganizationStep = self::createStep(
      key: OrganizationOnboardingStep::CREATE_ORGANIZATION,
      label: 'Create organization',
      actionMethod: 'POST',
      actionPath: '/api/organizations',
    );
    $inviteMembersStep = self::createStep(
      key: OrganizationOnboardingStep::INVITE_MEMBERS,
      label: 'Invite members',
      actionMethod: 'POST',
      actionPath: null !== $state->targetOrganizationId
        ? sprintf('/api/organizations/%s/invitations', $state->targetOrganizationId)
        : '/api/organizations/{organizationId}/invitations',
    );

    // invite_members is skippable
    $inviteMembersStep->skippable = true;

    $isCreateCompleted = in_array(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->completedSteps, true);

    if (!$isCreateCompleted) {
      $createOrganizationStep->status = 'pending';
      $createOrganizationStep->required = true;
      $createOrganizationStep->available = true;

      $inviteMembersStep->status = 'blocked';
      $inviteMembersStep->required = false;
      $inviteMembersStep->available = false;
      $inviteMembersStep->reason = 'organization_required';
    } else {
      $createOrganizationStep->status = 'completed';
      $createOrganizationStep->required = false;
      $createOrganizationStep->available = true;

      $isSkipped = in_array(OrganizationOnboardingStep::INVITE_MEMBERS, $state->skippedSteps, true);
      $isCompleted = in_array(OrganizationOnboardingStep::INVITE_MEMBERS, $state->completedSteps, true);

      if ($isSkipped) {
        $inviteMembersStep->status = 'skipped';
        $inviteMembersStep->required = false;
        $inviteMembersStep->available = true;
      } elseif ($isCompleted) {
        $inviteMembersStep->status = 'completed';
        $inviteMembersStep->required = false;
        $inviteMembersStep->available = true;
      } else {
        $inviteMembersStep->status = 'pending';
        $inviteMembersStep->required = false;
        $inviteMembersStep->available = true;
      }
    }

    // Hydrate completedAt timestamps
    $createOrganizationStep->completedAt = $completedAtByStep[OrganizationOnboardingStep::CREATE_ORGANIZATION] ?? null;
    $inviteMembersStep->completedAt = $completedAtByStep[OrganizationOnboardingStep::INVITE_MEMBERS] ?? null;

    // Rollback metadata
    $output->canRollback = $state->canRollback;
    $output->lastRollbackableStep = $state->lastRollbackableStep;
    $output->rollbackMethod = $state->canRollback ? 'POST' : null;
    $output->rollbackPath = $state->canRollback ? self::ROLLBACK_PATH : null;

    self::hydrateRollbackMetadata($createOrganizationStep, $state->lastRollbackableStep);
    self::hydrateRollbackMetadata($inviteMembersStep, $state->lastRollbackableStep);

    // Skip metadata: only available when the step is the current pending step
    self::hydrateSkipMetadata($createOrganizationStep, $state->nextStep);
    self::hydrateSkipMetadata($inviteMembersStep, $state->nextStep);

    $output->steps = [$createOrganizationStep, $inviteMembersStep];

    return $output;
  }

  /**
   * Method createStep.
   *
   * @since 1.0.0
   *
   * @param string $key the step key
   * @param string $label the step label
   * @param ?string $actionMethod HTTP method to execute this step
   * @param ?string $actionPath API path to execute this step
   *
   * @return OrganizationOnboardingStepOutput the initialized step DTO
   */
  private static function createStep(
    string $key,
    string $label,
    ?string $actionMethod = null,
    ?string $actionPath = null,
  ): OrganizationOnboardingStepOutput {
    $step = new OrganizationOnboardingStepOutput();
    $step->key = $key;
    $step->label = $label;
    $step->actionMethod = $actionMethod;
    $step->actionPath = $actionPath;

    return $step;
  }

  /**
   * Method hydrateRollbackMetadata.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingStepOutput $step the output step DTO
   * @param ?string $lastRollbackStep the currently rollbackable step key
   */
  private static function hydrateRollbackMetadata(
    OrganizationOnboardingStepOutput $step,
    ?string $lastRollbackStep,
  ): void {
    $isRollbackable = null !== $lastRollbackStep && $lastRollbackStep === $step->key;
    $step->rollbackAvailable = $isRollbackable;
    $step->rollbackMethod = $isRollbackable ? 'POST' : null;
    $step->rollbackPath = $isRollbackable ? self::ROLLBACK_PATH : null;
  }

  /**
   * Method hydrateSkipMetadata.
   *
   * Skip is available when the step is skippable and is the current pending step.
   *
   * @since 2.0.0
   *
   * @param OrganizationOnboardingStepOutput $step the output step DTO
   * @param ?string $nextStep the key of the current pending step
   */
  private static function hydrateSkipMetadata(
    OrganizationOnboardingStepOutput $step,
    ?string $nextStep,
  ): void {
    $canSkip = $step->skippable && null !== $nextStep && $nextStep === $step->key;
    $step->skipAvailable = $canSkip;
    $step->skipMethod = $canSkip ? 'POST' : null;
    $step->skipPath = $canSkip ? sprintf(self::SKIP_PATH_TEMPLATE, $step->key) : null;
  }
  // #endregion
}
