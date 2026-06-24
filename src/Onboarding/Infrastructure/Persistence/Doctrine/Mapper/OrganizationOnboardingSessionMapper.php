<?php

declare(strict_types=1);

namespace Onboarding\Infrastructure\Persistence\Doctrine\Mapper;

use Onboarding\Domain\Model\OrganizationOnboardingSession\{OrganizationOnboardingSession, StepHistoryEntry};
use Onboarding\Domain\Model\OrganizationOnboardingSession\RollbackAction\{RollbackActionFactory, RollbackActionInterface};
use Onboarding\Infrastructure\Persistence\Doctrine\Record\OrganizationOnboardingSessionRecord;

use function array_map;

/**
 * Mapper OrganizationOnboardingSessionMapper.
 *
 * @category Mapper
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOnboardingSessionMapper
{
  // #region Methods
  /**
   * Method toRecord.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSession $session the onboarding session aggregate
   *
   * @return OrganizationOnboardingSessionRecord the persistence record
   */
  public static function toRecord(OrganizationOnboardingSession $session): OrganizationOnboardingSessionRecord
  {
    $record = new OrganizationOnboardingSessionRecord();
    $record->id = $session->id();
    $record->userId = $session->userId();
    $record->flow = $session->flow();
    $record->state = $session->state();
    $record->nextStep = $session->nextStep();
    $record->blockedReason = $session->blockedReason();
    $record->targetOrganizationId = $session->targetOrganizationId();
    $record->targetOrganizationName = $session->targetOrganizationName();
    $record->completedSteps = $session->completedSteps();
    $record->skippedSteps = $session->skippedSteps();
    $record->rollbackStack = array_map(
      static fn (RollbackActionInterface $action): array => $action->toArray(),
      $session->rollbackStack(),
    );
    $record->stepHistory = array_map(
      static fn (StepHistoryEntry $entry): array => $entry->toArray(),
      $session->stepHistory(),
    );
    $record->createdAt = $session->createdAt();
    $record->updatedAt = $session->updatedAt();
    $record->dismissedAt = $session->dismissedAt();

    return $record;
  }

  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param OrganizationOnboardingSessionRecord $record the persistence record
   *
   * @return OrganizationOnboardingSession the domain aggregate
   */
  public static function toDomain(OrganizationOnboardingSessionRecord $record): OrganizationOnboardingSession
  {
    $rollbackStack = array_map(
      static fn (array $data): RollbackActionInterface => RollbackActionFactory::fromArray($data),
      $record->rollbackStack,
    );

    $stepHistory = array_map(
      static fn (array $data): StepHistoryEntry => StepHistoryEntry::fromArray($data),
      $record->stepHistory,
    );

    return OrganizationOnboardingSession::reconstitute(
      id: $record->id,
      userId: $record->userId,
      flow: $record->flow,
      state: $record->state,
      nextStep: $record->nextStep,
      blockedReason: $record->blockedReason,
      targetOrganizationId: $record->targetOrganizationId,
      targetOrganizationName: $record->targetOrganizationName,
      completedSteps: $record->completedSteps,
      skippedSteps: $record->skippedSteps,
      rollbackStack: $rollbackStack,
      stepHistory: $stepHistory,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      dismissedAt: $record->dismissedAt,
    );
  }
  // #endregion
}
