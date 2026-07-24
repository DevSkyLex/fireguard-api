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
    $output->dismissed = $state->dismissed;
    $output->dismissedAt = $state->dismissedAt;

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

    $orgId = $state->targetOrganizationId;
    $isCreateCompleted = in_array(OrganizationOnboardingStep::CREATE_ORGANIZATION, $state->completedSteps, true);

    // Step catalog: key => [label, required, skippable, actionMethod, actionPath]
    $stepCatalog = [
      OrganizationOnboardingStep::CREATE_ORGANIZATION => [
        'label' => 'Create organization',
        'required' => true,
        'skippable' => false,
        'actionMethod' => 'POST',
        'actionPath' => '/api/organizations',
      ],
      OrganizationOnboardingStep::SELECT_PLAN => [
        'label' => 'Choose a plan',
        'required' => false,
        'skippable' => true,
        'actionMethod' => 'POST',
        'actionPath' => null !== $orgId
          ? sprintf('/api/organizations/%s/billing/checkout', $orgId)
          : '/api/organizations/{organizationId}/billing/checkout',
      ],
      OrganizationOnboardingStep::INVITE_MEMBERS => [
        'label' => 'Invite members',
        'required' => false,
        'skippable' => true,
        'actionMethod' => 'POST',
        'actionPath' => null !== $orgId
          ? sprintf('/api/organizations/%s/invitations', $orgId)
          : '/api/organizations/{organizationId}/invitations',
      ],
      OrganizationOnboardingStep::CREATE_FIRST_FACILITY => [
        'label' => 'Create first facility',
        'required' => true,
        'skippable' => false,
        'actionMethod' => 'POST',
        'actionPath' => null !== $orgId
          ? sprintf('/api/organizations/%s/facilities', $orgId)
          : '/api/organizations/{organizationId}/facilities',
      ],
      OrganizationOnboardingStep::CREATE_FIRST_EQUIPMENT => [
        'label' => 'Create first equipment',
        'required' => true,
        'skippable' => false,
        'actionMethod' => 'POST',
        'actionPath' => null !== $orgId
          ? sprintf('/api/organizations/%s/equipment', $orgId)
          : '/api/organizations/{organizationId}/equipment',
      ],
    ];

    $steps = [];
    foreach (OrganizationOnboardingStep::all() as $stepKey) {
      $meta = $stepCatalog[$stepKey];

      $step = self::createStep(
        key: $stepKey,
        label: $meta['label'],
        actionMethod: $meta['actionMethod'],
        actionPath: $meta['actionPath'],
      );
      $step->skippable = $meta['skippable'];

      $isCompleted = in_array($stepKey, $state->completedSteps, true);
      $isSkipped = in_array($stepKey, $state->skippedSteps, true);

      if ($isCompleted) {
        $step->status = 'completed';
        $step->required = false;
        $step->available = true;
      } elseif ($isSkipped) {
        $step->status = 'skipped';
        $step->required = false;
        $step->available = true;
      } elseif (!$isCreateCompleted) {
        // Before create_organization is confirmed, all subsequent steps are blocked
        if (OrganizationOnboardingStep::CREATE_ORGANIZATION === $stepKey) {
          $step->status = 'pending';
          $step->required = true;
          $step->available = true;
        } else {
          $step->status = 'blocked';
          $step->required = $meta['required'];
          $step->available = false;
          $step->reason = 'organization_required';
        }
      } elseif ($stepKey === $state->nextStep) {
        $step->status = 'pending';
        $step->required = $meta['required'];
        $step->available = true;
      } else {
        // Future step not yet reachable
        $step->status = 'blocked';
        $step->required = $meta['required'];
        $step->available = false;
        $step->reason = 'previous_step_required';
      }

      // Only expose completedAt for steps that are actually confirmed or skipped;
      // history entries from a rolled-back or externally-reset session must not
      // leak a timestamp for a step that is no longer in a terminal state.
      $step->completedAt = ($isCompleted || $isSkipped)
        ? ($completedAtByStep[$stepKey] ?? null)
        : null;

      self::hydrateRollbackMetadata($step, $state->lastRollbackableStep);
      self::hydrateSkipMetadata($step, $state->nextStep);

      $steps[] = $step;
    }

    $output->steps = $steps;

    // Rollback metadata
    $output->canRollback = $state->canRollback;
    $output->lastRollbackableStep = $state->lastRollbackableStep;
    $output->rollbackMethod = $state->canRollback ? 'POST' : null;
    $output->rollbackPath = $state->canRollback ? self::ROLLBACK_PATH : null;

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
