<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Workflow;

use DateTimeImmutable;
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowMutation,
  InterventionWorkflowView
};
use Intervention\Domain\Exception\{
  InterventionConflictException,
  InterventionPreconditionFailedException,
  InterventionValidationException
};
use Intervention\Domain\Service\InterventionChangePolicy;
use Intervention\Domain\ValueObject\{InterventionChangeStatus, InterventionStatus};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionChangeRecord,
  InterventionRecord,
  InterventionWorkItemRecord
};

use function array_key_exists;

/**
 * Persists proposed intervention changes within the gateway transaction.
 */
final readonly class InterventionWorkflowChangeWriter
{
  public function __construct(
    private InterventionWorkflowWriterRuntime $runtime,
    private InterventionChangePolicy $changePolicy,
  ) {
  }

  /**
   * Method mutateChange.
   *
   * Executes the mutate change operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   *
   * @return ?InterventionWorkflowView the mutate change result
   */
  public function mutateChange(InterventionWorkflowMutation $mutation): ?InterventionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createChange($mutation);
    }
    $record = $this->runtime->support->change($mutation->id);
    $intervention = $this->runtime->support->owningIntervention($record);
    $this->runtime->support->assertRevision($record->revision, $mutation->expectedRevision);
    $this->assertCanMutateChange($intervention, $record->workItem, $mutation->userId);
    if ('delete' === $mutation->action) {
      $this->changePolicy->assertCanDelete(InterventionStatus::from($intervention->status));
      if ('proposed' !== $record->status) {
        throw new InterventionConflictException('Only proposed intervention changes can be deleted.');
      }
      $this->runtime->entityManager->remove($record);
      $this->runtime->support->touch($intervention, new DateTimeImmutable());
      $this->runtime->entityManager->flush();

      return null;
    }

    return $this->updateChange($intervention, $record, $mutation);
  }

  /**
   * Applies the requested fields to an existing proposed change.
   *
   * @since 1.1.0
   *
   * @param InterventionRecord $intervention the owning intervention
   * @param InterventionChangeRecord $record the existing change
   * @param InterventionWorkflowMutation $mutation the requested mutation
   *
   * @return InterventionWorkflowView the resulting change view
   */
  private function updateChange(
    InterventionRecord $intervention,
    InterventionChangeRecord $record,
    InterventionWorkflowMutation $mutation,
  ): InterventionWorkflowView {
    if ([] === $mutation->payload) {
      return $this->runtime->views->changeView($record);
    }
    if (array_key_exists('patch', $mutation->payload)) {
      $this->changePolicy->assertCanEditPatch(InterventionStatus::from($intervention->status));
      if ('proposed' !== $record->status) {
        throw new InterventionConflictException('Only proposed changes can be edited.');
      }
      $record->patch = InterventionWorkflowPayload::patch($mutation->payload['patch']);
    }
    if (array_key_exists('status', $mutation->payload)) {
      $status = InterventionWorkflowPayload::requiredString($mutation->payload, 'status');
      $nextChangeStatus = InterventionChangeStatus::from($status);
      $this->changePolicy->assertCanChangeStatus(InterventionStatus::from($intervention->status), $nextChangeStatus);
      $this->changePolicy->assertTransitionAllowed(InterventionChangeStatus::from($record->status), $nextChangeStatus);
      $record->status = $nextChangeStatus->value;
    }
    $now = new DateTimeImmutable();
    ++$record->revision;
    $record->updatedAt = $now;
    $this->runtime->support->touch($intervention, $now);
    $this->runtime->entityManager->flush();

    return $this->runtime->views->changeView($record);
  }

  /**
   * Method createChange.
   *
   * Executes the create change operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   *
   * @return InterventionWorkflowView the create change result
   */
  private function createChange(InterventionWorkflowMutation $mutation): InterventionWorkflowView
  {
    $id = $mutation->id ?? $this->runtime->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->runtime->entityManager->find(InterventionChangeRecord::class, $id) instanceof InterventionChangeRecord) {
      throw new InterventionPreconditionFailedException('The client UUID intervention change already exists.');
    }
    $intervention = $this->runtime->support->intervention(InterventionWorkflowPayload::requiredString($mutation->payload, 'interventionId'));
    $this->changePolicy->assertCanCreate(InterventionStatus::from($intervention->status));
    $workItemId = InterventionWorkflowPayload::nullableString($mutation->payload, 'workItemId');
    $workItem = null;
    if (null !== $workItemId) {
      $workItem = $this->runtime->entityManager->find(InterventionWorkItemRecord::class, $workItemId);
      if (!$workItem instanceof InterventionWorkItemRecord || $workItem->intervention?->id !== $intervention->id) {
        throw new InterventionValidationException('Intervention changes can only reference work items from the same intervention.');
      }
    }
    $this->assertCanMutateChange($intervention, $workItem, $mutation->userId);
    $now = new DateTimeImmutable();
    $record = new InterventionChangeRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->workItem = $workItem;
    $record->resource = InterventionWorkflowPayload::requiredString($mutation->payload, 'resource');
    $record->patch = InterventionWorkflowPayload::patch($mutation->payload['patch'] ?? null);
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->runtime->support->touch($intervention, $now);
    $this->runtime->entityManager->persist($record);
    $this->runtime->entityManager->flush();

    return $this->runtime->views->changeView($record);
  }

  /**
   * Method assertCanMutateChange.
   *
   * Ensures proposed changes created during execution are owned by the
   * assigned member or an active intervention participant.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   * @param ?InterventionWorkItemRecord $workItem the work item value
   * @param string $userId the current user id value
   */
  private function assertCanMutateChange(
    InterventionRecord $intervention,
    ?InterventionWorkItemRecord $workItem,
    string $userId,
  ): void {
    if ('draft' === $intervention->status || 'submitted' === $intervention->status) {
      return;
    }
    $this->runtime->memberPolicy->assertCanExecuteWorkItem(
      $this->runtime->support->organizationId($intervention),
      $userId,
      $intervention->responsibleId,
      $intervention->participants,
      $workItem?->assigneeId,
    );
  }
}
