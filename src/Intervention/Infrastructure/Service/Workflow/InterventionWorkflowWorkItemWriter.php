<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Workflow;

use DateTimeImmutable;
use Intervention\Application\Contract\Activity\{InterventionActivityAppendRequest, InterventionActivityContent};
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowMutation,
  InterventionWorkflowView
};
use Intervention\Domain\Event\Workflow\{InterventionStatusTransitionedEvent, InterventionWorkflowNotificationRequestedEvent};
use Intervention\Domain\Exception\{
  InterventionConflictException,
  InterventionPreconditionFailedException,
  InterventionValidationException
};
use Intervention\Domain\Service\InterventionWorkItemTransitionPolicy;
use Intervention\Domain\ValueObject\InterventionWorkItemStatus;
use Intervention\Domain\ValueObject\{WorkItemEffort, WorkItemExecutionResult, WorkItemPeriod};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionRecord,
  InterventionWorkItemRecord
};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionWorkItemAssignmentRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_diff;
use function array_intersect;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function in_array;
use function is_array;
use function preg_match;
use function trim;

/**
 * Class InterventionWorkflowWorkItemWriter
 *
 * Persists workflow changes to work items and records assignment history.
 *
 * @category Service
 */
final readonly class InterventionWorkflowWorkItemWriter
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies workflow collaborators for work-item mutation, policy and event dispatch.
   *
   * @access public
   *
   * @param InterventionWorkflowWriterRuntime $runtime persistence and workflow collaborators
   * @param InterventionWorkItemTransitionPolicy $workItemTransitionPolicy validates status changes
   * @param EventDispatcherPort $eventDispatcher records workflow events in the main outbox
   *
   * @return void
   */
  public function __construct(
    private InterventionWorkflowWriterRuntime $runtime,
    private InterventionWorkItemTransitionPolicy $workItemTransitionPolicy,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method mutateWorkItem.
   *
   * Executes the mutate work item operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications effects persisted into the outbox before commit
   *
   * @return ?InterventionWorkflowView the mutate work item result
   */
  public function mutateWorkItem(InterventionWorkflowMutation $mutation, array &$notifications): ?InterventionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createWorkItem($mutation, $notifications);
    }
    $record = $this->runtime->support->workItem($mutation->id);
    $intervention = $this->runtime->support->owningIntervention($record);
    $this->runtime->support->assertRevision($record->revision, $mutation->expectedRevision);
    $this->runtime->support->assertInterventionWorkMutable($intervention);
    $this->assertWorkItemMutationAllowed($record, $intervention, $mutation);
    if ('delete' === $mutation->action) {
      $this->deleteWorkItem($record, $intervention);

      return null;
    }
    $previousAssigneeId = $record->assigneeId;
    $previousWorkItemStatus = $record->status;
    $interventionAutoStarted = $this->applyWorkItemPatch($record, $intervention, $mutation->payload, $previousWorkItemStatus);
    $this->applyExecutionResult($record, $intervention, $mutation);
    $now = new DateTimeImmutable();
    ++$record->revision;
    $record->updatedAt = $now;
    $this->runtime->support->touch($intervention, $now);
    if ($record->assigneeId !== $previousAssigneeId) {
      $this->recordAssignment($record, $previousAssigneeId, $mutation->userId, $now);
    }
    $this->runtime->entityManager->flush();
    if ($interventionAutoStarted) {
      $this->recordWorkItemAutoStart($intervention, $mutation->userId, $notifications);
    }
    if (null !== $record->assigneeId && $record->assigneeId !== $previousAssigneeId) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $assigneeId = $record->assigneeId;
      $notifications[] = fn () => $this->eventDispatcher->dispatch(new InterventionWorkflowNotificationRequestedEvent('assigned', $interventionId, $interventionName, $assigneeId));
    }

    return $this->runtime->views->workItemView($record);
  }

  /**
   * Method assertWorkItemMutationAllowed
   *
   * Enforces assignment and execution rules for changes to existing work items.
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $record work item being changed
   * @param InterventionRecord $intervention owning intervention
   * @param InterventionWorkflowMutation $mutation requested mutation and actor
   *
   * @return void
   *
   * @throws InterventionConflictException when a finished item cannot be reassigned or edited
   */
  private function assertWorkItemMutationAllowed(InterventionWorkItemRecord $record, InterventionRecord $intervention, InterventionWorkflowMutation $mutation): void
  {
    if ('draft' !== $intervention->status && !$this->isWorkItemPlanningOnly($mutation)) {
      $this->runtime->memberPolicy->assertCanExecuteWorkItem(
        $this->runtime->support->organizationId($intervention),
        $mutation->userId,
        $intervention->responsibleId,
        $intervention->participants,
        $record->assigneeId,
      );
    }
    if (array_key_exists('assigneeId', $mutation->payload) && in_array($record->status, ['completed', 'skipped'], true)) {
      throw new InterventionConflictException('Finished work items cannot be reassigned.');
    }
    if (in_array($record->status, ['completed', 'skipped'], true)
      && in_array($mutation->payload['status'] ?? $record->status, ['completed', 'skipped'], true)
      && [] !== array_intersect(array_keys($mutation->payload), ['estimatedMinutes', 'remainingMinutes', 'workStartsOn', 'workEndsOn'])) {
      throw new InterventionConflictException('Reopen the task before changing its effort or period. Time may still be recorded independently.');
    }
  }

  /**
   * Method deleteWorkItem
   *
   * Deletes a prepared work item without time history and updates its parent timestamp.
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $record work item to remove
   * @param InterventionRecord $intervention owning intervention
   *
   * @return void
   *
   * @throws InterventionConflictException when the intervention is beyond draft state
   */
  private function deleteWorkItem(InterventionWorkItemRecord $record, InterventionRecord $intervention): void
  {
    if ('draft' !== $intervention->status) {
      throw new InterventionConflictException('Only prepared work items can be deleted.');
    }
    $this->runtime->support->assertNoTimeHistory($intervention, $record);
    $this->runtime->entityManager->remove($record);
    $this->runtime->support->touch($intervention, new DateTimeImmutable());
    $this->runtime->entityManager->flush();
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function applyWorkItemPatch(InterventionWorkItemRecord $record, InterventionRecord $intervention, array $payload, string $previousStatus): bool
  {
    $interventionAutoStarted = false;
    if (array_key_exists('status', $payload)) {
      $status = InterventionWorkflowPayload::requiredString($payload, 'status');
      $skipReason = InterventionWorkflowPayload::nullableString($payload, 'skipReason');
      $nextWorkItemStatus = InterventionWorkItemStatus::from($status);
      $this->workItemTransitionPolicy->assertAllowed(InterventionWorkItemStatus::from($record->status), $nextWorkItemStatus, $skipReason);
      $record->status = $nextWorkItemStatus->value;
      if ('planned' === $intervention->status && 'planned' !== $status) {
        $intervention->status = 'in_progress';
        $interventionAutoStarted = true;
      }
    }
    if (array_key_exists('skipReason', $payload)) {
      $skipReason = InterventionWorkflowPayload::nullableString($payload, 'skipReason');
      $record->skipReason = null === $skipReason ? null : trim($skipReason);
    }
    if (array_key_exists('resultResource', $payload)) {
      $record->resultResource = InterventionWorkflowPayload::nullableString($payload, 'resultResource');
      $this->runtime->support->assertFacilityTarget($record->resultResource, $intervention);
    }
    if (array_key_exists('assigneeId', $payload)) {
      $record->assigneeId = InterventionWorkflowPayload::nullableString($payload, 'assigneeId');
      if (null !== $record->assigneeId) {
        $this->runtime->memberPolicy->assertActiveMember($this->runtime->support->organizationId($intervention), $record->assigneeId);
      }
    }
    $this->applyWorkItemEffort($record, $intervention, $payload, false);
    if (in_array($previousStatus, ['completed', 'skipped'], true)
      && !in_array($record->status, ['completed', 'skipped'], true)
      && !array_key_exists('remainingMinutes', $payload)) {
      $record->remainingMinutes = null;
    }

    return $interventionAutoStarted;
  }

  /**
   * @param list<callable(): void> $notifications
   */
  private function recordWorkItemAutoStart(InterventionRecord $intervention, string $actorUserId, array &$notifications): void
  {
    // Starting work advances planned -> in_progress and journals that change.
    $organizationId = $this->runtime->support->organizationId($intervention);
    $this->runtime->activities->append(new InterventionActivityAppendRequest(
      $intervention->id,
      $organizationId,
      $this->runtime->memberPolicy->findMemberId($organizationId, $actorUserId),
      new InterventionActivityContent('system', 'status_changed', null, ['from' => 'planned', 'to' => 'in_progress']),
    ));
    // Persist the audit event in the transaction; delivery follows commit.
    $interventionId = $intervention->id;
    $interventionNumber = $intervention->number;
    $notifications[] = fn () => $this->eventDispatcher->dispatch(new InterventionStatusTransitionedEvent(
      organizationId: $organizationId,
      interventionId: $interventionId,
      interventionNumber: $interventionNumber,
      actorUserId: $actorUserId,
      fromStatus: 'planned',
      toStatus: 'in_progress',
    ));
  }

  /**
   * Method applyWorkItemEffort.
   *
   * Validates task effort and local dates without deriving remaining effort from actual time.
   *
   * @since 1.1.0
   *
   * @param InterventionWorkItemRecord $record persisted record being mapped or updated
   * @param InterventionRecord $intervention owning intervention and its operational planning window
   * @param array<string, mixed> $payload
   * @param bool $creating whether to initialize remaining effort from the reference estimate
   *
   * @return void completes without returning a value
   */
  private function applyWorkItemEffort(InterventionWorkItemRecord $record, InterventionRecord $intervention, array $payload, bool $creating): void
  {
    if (array_key_exists('estimatedMinutes', $payload)) {
      $record->estimatedMinutes = WorkItemEffort::minutes($payload['estimatedMinutes']);
    }
    if ($creating) {
      $record->remainingMinutes = $record->estimatedMinutes;
    } elseif (array_key_exists('remainingMinutes', $payload)) {
      $record->remainingMinutes = WorkItemEffort::minutes($payload['remainingMinutes']);
    }
    $period = new WorkItemPeriod(
      array_key_exists('workStartsOn', $payload) ? InterventionWorkflowPayload::nullableString($payload, 'workStartsOn') : $record->workStartsOn,
      array_key_exists('workEndsOn', $payload) ? InterventionWorkflowPayload::nullableString($payload, 'workEndsOn') : $record->workEndsOn,
    );
    $timezone = $this->runtime->support->organizationTimezone($this->runtime->support->organizationId($intervention));
    if (!$period->fitsWithin($intervention->plannedStartAt?->setTimezone($timezone)->format('Y-m-d'), $intervention->dueAt?->setTimezone($timezone)->format('Y-m-d'))) {
      throw new InterventionValidationException('The task period must be within the intervention period.');
    }
    $record->workStartsOn = $period->startsOn;
    $record->workEndsOn = $period->endsOn;
  }

  /**
   * Distinguishes planning-only updates from execution or remaining-effort changes.
   *
   * @since 1.1.0
   *
   * @param InterventionWorkflowMutation $mutation requested operational mutation, including any explicit overload consent
   *
   * @return bool whether only planner-owned task fields are changed
   */
  private function isWorkItemPlanningOnly(InterventionWorkflowMutation $mutation): bool
  {
    return 'update' === $mutation->action
      && [] !== $mutation->payload
      && [] === array_diff(array_keys($mutation->payload), ['assigneeId', 'estimatedMinutes', 'workStartsOn', 'workEndsOn', 'workloadConfirmationToken']);
  }

  /**
   * Records the assignment transition while preserving access to historical contributions.
   *
   * @since 1.1.0
   *
   * @param InterventionWorkItemRecord $item work item whose assignment or retained history is being checked
   * @param ?string $previousMember previously observed assignee, or null when unassigned
   * @param string $userId authenticated account identifier used for authorization
   * @param DateTimeImmutable $now timestamp of the current operation
   *
   * @return void completes without returning a value
   */
  private function recordAssignment(InterventionWorkItemRecord $item, ?string $previousMember, string $userId, DateTimeImmutable $now): void
  {
    $parent = $this->runtime->support->owningIntervention($item, false);
    $organizationId = $this->runtime->support->organizationId($parent);
    $actor = $this->runtime->memberPolicy->findMemberId($organizationId, $userId);
    $history = $this->runtime->entityManager->getRepository(InterventionWorkItemAssignmentRecord::class)->findBy(['workItem' => $item, 'unassignedAt' => null]);
    foreach ($history as $assignment) {
      $assignment->unassignedAt = $now;
    }
    // Imports can predate assignment tracking. Record only the observed prior
    // assignee at this transition; never invent an earlier assignment date.
    if ([] === $history && null !== $previousMember) {
      $previous = new InterventionWorkItemAssignmentRecord();
      $previous->id = $this->runtime->uuidFactory->generateRaw();
      $previous->workItem = $item;
      $previous->memberId = $previousMember;
      $previous->assignedAt = $now;
      $previous->unassignedAt = $now;
      $previous->actorId = $actor;
      $this->runtime->entityManager->persist($previous);
    }
    if (null !== $item->assigneeId) {
      $assignment = new InterventionWorkItemAssignmentRecord();
      $assignment->id = $this->runtime->uuidFactory->generateRaw();
      $assignment->workItem = $item;
      $assignment->memberId = $item->assigneeId;
      $assignment->assignedAt = $now;
      $assignment->actorId = $actor;
      $this->runtime->entityManager->persist($assignment);
    }
    $this->runtime->activities->append(new InterventionActivityAppendRequest(
      $parent->id,
      $organizationId,
      $actor,
      new InterventionActivityContent('system', 'work_item_reassigned', null, ['workItemId' => $item->id, 'from' => $previousMember, 'to' => $item->assigneeId]),
    ));
  }

  /**
   * Method createWorkItem.
   *
   * Executes the create work item operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications effects persisted into the outbox before commit
   *
   * @return InterventionWorkflowView the create work item result
   */
  private function createWorkItem(InterventionWorkflowMutation $mutation, array &$notifications): InterventionWorkflowView
  {
    $id = $mutation->id ?? $this->runtime->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->runtime->entityManager->find(InterventionWorkItemRecord::class, $id) instanceof InterventionWorkItemRecord) {
      throw new InterventionPreconditionFailedException('The client UUID work item already exists.');
    }
    $intervention = $this->runtime->support->intervention(InterventionWorkflowPayload::requiredString($mutation->payload, 'interventionId'));
    $this->runtime->support->assertInterventionWorkMutable($intervention);
    $source = InterventionWorkflowPayload::requiredString($mutation->payload, 'source');
    if ('draft' !== $intervention->status && 'discovered' !== $source) {
      throw new InterventionConflictException('Only discovered work items can be added after preparation.');
    }
    if ('draft' !== $intervention->status) {
      $this->runtime->memberPolicy->assertCanExecuteWorkItem(
        $this->runtime->support->organizationId($intervention),
        $mutation->userId,
        $intervention->responsibleId,
        $intervention->participants,
        null,
      );
    }
    $assigneeId = InterventionWorkflowPayload::nullableString($mutation->payload, 'assigneeId');
    if (null !== $assigneeId) {
      $this->runtime->memberPolicy->assertActiveMember($this->runtime->support->organizationId($intervention), $assigneeId);
    }
    $now = new DateTimeImmutable();
    $record = new InterventionWorkItemRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->action = InterventionWorkflowPayload::requiredString($mutation->payload, 'action');
    $record->target = InterventionWorkflowPayload::nullableString($mutation->payload, 'target');
    $record->resultResource = InterventionWorkflowPayload::nullableString($mutation->payload, 'resultResource');
    $record->operationId = InterventionWorkflowPayload::nullableString($mutation->payload, 'operationId');
    $record->occurrenceId = InterventionWorkflowPayload::nullableString($mutation->payload, 'occurrenceId');
    $record->operationKind = InterventionWorkflowPayload::nullableString($mutation->payload, 'operationKind');
    $hasSource = null !== $record->operationId || null !== $record->occurrenceId || null !== $record->operationKind;
    if ($hasSource && (null === $record->operationId || null === $record->occurrenceId || !in_array($record->operationKind, ['control', 'maintenance'], true))) {
      throw new InterventionValidationException('A preventive source requires its operation, occurrence and kind.');
    }
    if ($hasSource && (('control' === $record->operationKind && 'inspection' !== $record->action) || ('maintenance' === $record->operationKind && 'maintenance' !== $record->action))) {
      throw new InterventionValidationException('The work item action must match its preventive operation kind.');
    }
    if ($hasSource || in_array($record->action, ['maintenance', 'repair', 'replacement'], true)) {
      $this->assertEquipmentTarget($record, $intervention);
    }
    $this->runtime->support->assertFacilityTarget($record->target, $intervention);
    $this->runtime->support->assertFacilityTarget($record->resultResource, $intervention);
    $record->assigneeId = $assigneeId;
    $record->source = $source;
    $record->required = (bool) ($mutation->payload['required'] ?? true);
    $this->applyWorkItemEffort($record, $intervention, $mutation->payload, true);
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->runtime->support->touch($intervention, $now);
    $this->runtime->entityManager->persist($record);
    if (null !== $record->assigneeId) {
      $this->recordAssignment($record, null, $mutation->userId, $now);
    }
    $this->runtime->entityManager->flush();
    if (null !== $record->assigneeId) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $assigneeId = $record->assigneeId;
      $notifications[] = fn () => $this->eventDispatcher->dispatch(new InterventionWorkflowNotificationRequestedEvent('assigned', $interventionId, $interventionName, $assigneeId));
    }

    return $this->runtime->views->workItemView($record);
  }

  /**
   * Method applyExecutionResult
   *
   * Stages executor-attributed facts and keeps unsuccessful repairs open.
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $record locked work item
   * @param InterventionRecord $intervention owning intervention
   * @param InterventionWorkflowMutation $mutation authenticated execution mutation
   *
   * @return void
   */
  private function applyExecutionResult(InterventionWorkItemRecord $record, InterventionRecord $intervention, InterventionWorkflowMutation $mutation): void
  {
    $operational = in_array($record->action, ['maintenance', 'repair', 'replacement'], true);
    if (array_key_exists('executionResult', $mutation->payload)) {
      if (!$operational || 'draft' === $intervention->status) {
        throw new InterventionValidationException('Execution results belong to equipment operations after preparation.');
      }
      $payload = $mutation->payload['executionResult'];
      if (null === $payload) {
        if (null !== $record->executionResult) {
          throw new InterventionValidationException('Recorded attempts are retained. Reopen the task and record a corrected attempt.');
        }
        $record->executionResult = null;
      } elseif (is_array($payload)) {
        $result = WorkItemExecutionResult::fromPayload(InterventionWorkflowPayload::patch($payload));
        $result->assertAlreadyPerformed(new DateTimeImmutable());
        $equipmentId = $this->assertEquipmentTarget($record, $intervention);
        if ($result->equipmentId !== $equipmentId) {
          throw new InterventionValidationException('The result must concern the prepared equipment.');
        }
        $authorId = $this->runtime->memberPolicy->findMemberId($this->runtime->support->organizationId($intervention), $mutation->userId);
        if (null === $authorId) {
          throw new InterventionValidationException('The executor must be an organization member.');
        }
        $previous = $record->executionResult;
        $fact = $result->toArray();
        if (null === $previous || array_intersect_key($previous, $fact) !== $fact) {
          $history = is_array($previous['history'] ?? null) ? $previous['history'] : [];
          if (null !== $previous) {
            unset($previous['history']);
            $history[] = $previous;
          }
          $record->executionResult = [...$result->toArray(), 'authorId' => $authorId, 'operationId' => $record->operationId, 'occurrenceId' => $record->occurrenceId, 'state' => 'staged', 'validatedAt' => null, 'history' => $history];
          $activityResult = $record->executionResult;
          unset($activityResult['history']);
          $this->runtime->activities->append(new InterventionActivityAppendRequest($intervention->id, $this->runtime->support->organizationId($intervention), $authorId, new InterventionActivityContent('system', 'work_item_result_recorded', null, ['workItemId' => $record->id, 'result' => $activityResult])));
        }
      } else {
        throw new InterventionValidationException('The execution result must be an object.');
      }
    }
    if ($operational && 'completed' === $record->status) {
      if (null === $record->executionResult) {
        throw new InterventionValidationException('Completing this operation requires a successful execution result.');
      }
      WorkItemExecutionResult::fromPayload($record->executionResult)->assertCompletesAction($record->action, $this->assertEquipmentTarget($record, $intervention));
      if ('replacement' === $record->action && null === $record->resultResource) {
        throw new InterventionValidationException('A completed replacement must identify its successor equipment.');
      }
    }
  }

  /**
   * Method assertEquipmentTarget
   *
   * Requires a real equipment target belonging to the intervention organization.
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $record equipment operation
   * @param InterventionRecord $intervention owning intervention
   *
   * @return string scoped equipment identifier
   */
  private function assertEquipmentTarget(InterventionWorkItemRecord $record, InterventionRecord $intervention): string
  {
    if (null === $record->target || 1 !== preg_match('#^/api/equipment/([^/]+)$#', $record->target, $match)
      || !$this->runtime->support->equipmentBelongsToOrganization($match[1], $intervention)) {
      throw new InterventionValidationException('An equipment operation requires an equipment in the intervention organization.');
    }

    return $match[1];
  }
  // #endregion
}
