<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

use DateTimeInterface;
use Intervention\Application\Contract\Activity\{InterventionActivityAppendRequest, InterventionActivityContent};
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowMutation,
  InterventionWorkflowView
};
use Intervention\Application\Service\InterventionDraftPublisher;
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use Intervention\Domain\Exception\{
  InterventionAccessDeniedException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionPreconditionFailedException,
  InterventionValidationException
};
use Intervention\Domain\Model\Intervention\{
  Intervention as InterventionAggregate,
  InterventionContent,
  InterventionCreation,
  InterventionOwnership,
  InterventionOwnershipChanges,
  InterventionPatch,
  InterventionSchedule,
  InterventionScheduleChanges,
  InterventionTextChanges
};
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Domain\ValueObject\{InterventionPriority, InterventionStatus, InterventionType};
use Intervention\Domain\ValueObject\WorkItemPeriod;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionRecord
};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function array_key_exists;
use function implode;
use function in_array;

/**
 * Persists intervention creation, edits, transitions and their deferred effects.
 */
final readonly class InterventionWorkflowInterventionWriter
{
  public function __construct(
    private InterventionWorkflowWriterRuntime $runtime,
    private InterventionTransitionPolicy $transitionPolicy,
    private InterventionDraftPublisher $draftPublisher,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method mutateIntervention.
   *
   * Executes the mutate intervention operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return ?InterventionWorkflowView the mutate intervention result
   */
  public function mutateIntervention(InterventionWorkflowMutation $mutation, array &$notifications): ?InterventionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createIntervention($mutation);
    }
    $intervention = $this->runtime->support->intervention($mutation->id);
    $this->runtime->support->assertRevision($intervention->revision, $mutation->expectedRevision);
    if ('delete' === $mutation->action) {
      if (!in_array($intervention->status, ['draft', 'abandoned'], true)) {
        throw new InterventionConflictException('Only draft or abandoned interventions can be deleted.');
      }
      $this->runtime->support->assertNoTimeHistory($intervention);
      // Purge any still-draft resource records this intervention created before
      // removing it, so no orphaned drafts (and their unique client ids) survive.
      $this->draftPublisher->discard($intervention->id);
      $this->runtime->entityManager->remove($intervention);
      $this->runtime->entityManager->flush();

      return null;
    }

    return $this->updateIntervention($intervention, $mutation, $notifications);
  }

  /**
   * Method createIntervention.
   *
   * Executes the create intervention operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   *
   * @return InterventionWorkflowView the create intervention result
   */
  private function createIntervention(InterventionWorkflowMutation $mutation): InterventionWorkflowView
  {
    $id = $mutation->id ?? $this->runtime->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->runtime->entityManager->find(InterventionRecord::class, $id) instanceof InterventionRecord) {
      throw new InterventionPreconditionFailedException('The client UUID intervention already exists.');
    }
    $organizationId = InterventionWorkflowPayload::requiredString($mutation->payload, 'organizationId');
    $organization = $this->runtime->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($organizationId);
    }
    $responsibleId = InterventionWorkflowPayload::nullableString($mutation->payload, 'responsibleId');
    $participants = InterventionWorkflowPayload::stringList($mutation->payload['participants'] ?? []);
    $this->runtime->support->assertActiveMembers($organizationId, $responsibleId, $participants);
    $siteId = InterventionWorkflowPayload::nullableString($mutation->payload, 'siteId');
    $this->runtime->support->assertSiteBelongsToOrganization($siteId, $organizationId);
    $aggregate = InterventionAggregate::create(new InterventionCreation(
      id: $id,
      organizationId: $organizationId,
      type: InterventionType::from(InterventionWorkflowPayload::requiredString($mutation->payload, 'type')),
      content: new InterventionContent(
        InterventionWorkflowPayload::requiredString($mutation->payload, 'name'),
        InterventionWorkflowPayload::nullableString($mutation->payload, 'description'),
      ),
      ownership: new InterventionOwnership($siteId, $responsibleId, $participants),
      schedule: new InterventionSchedule(
        InterventionPriority::from(InterventionWorkflowPayload::requiredString($mutation->payload, 'priority')),
        InterventionWorkflowPayload::date($mutation->payload['plannedStartAt'] ?? null),
        InterventionWorkflowPayload::date($mutation->payload['dueAt'] ?? null),
      ),
    ));
    $intervention = InterventionMapper::toRecord($aggregate);
    $intervention->organization = $organization;
    $intervention->number = $this->runtime->support->allocateNumber($organizationId);
    if (array_key_exists('labelIds', $mutation->payload)) {
      foreach ($this->runtime->support->resolveLabels($mutation->payload['labelIds'] ?? [], $organizationId) as $label) {
        $intervention->labels->add($label);
      }
    }
    $this->runtime->entityManager->persist($intervention);
    $this->runtime->entityManager->flush();
    $this->runtime->activities->append(new InterventionActivityAppendRequest(
      $intervention->id,
      $organizationId,
      $this->runtime->memberPolicy->findMemberId($organizationId, $mutation->userId),
      new InterventionActivityContent('system', 'created', null, null),
    ));

    return $this->runtime->views->interventionView($intervention);
  }

  /**
   * Method updateIntervention.
   *
   * Executes the update intervention operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   * @param InterventionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return InterventionWorkflowView the update intervention result
   */
  private function updateIntervention(InterventionRecord $intervention, InterventionWorkflowMutation $mutation, array &$notifications): InterventionWorkflowView
  {
    $organizationId = $this->runtime->support->organizationId($intervention);
    $previousStatus = $intervention->status;
    $aggregate = InterventionMapper::toDomain($intervention);
    $previousPlannedStartAt = $aggregate->plannedStartAt();
    $previousDueAt = $aggregate->dueAt();
    $responsibleId = $this->resolveResponsibleId($aggregate, $mutation->payload, $organizationId);
    $participants = $this->resolveParticipants($aggregate, $mutation->payload, $organizationId);
    $siteId = $this->resolveSiteId($aggregate, $mutation->payload, $organizationId);
    $nextStatus = $this->resolveNextStatus($aggregate, $mutation, $organizationId, $previousStatus, $responsibleId);
    $this->applyInterventionPatch($aggregate, $mutation->payload, $siteId, $responsibleId, $participants, $nextStatus);
    $this->assertTaskPeriodsFit($intervention, $aggregate, $organizationId);
    InterventionMapper::sync($aggregate, $intervention);
    // A rescheduled due date invalidates any reminder already sent against the
    // old one: the anti-spam stamps must not silently suppress a reminder for
    // the new date.
    if ($previousDueAt?->getTimestamp() !== $aggregate->dueAt()?->getTimestamp()) {
      $intervention->dueSoonNotifiedAt = null;
      $intervention->overdueNotifiedAt = null;
    }
    if (array_key_exists('labelIds', $mutation->payload)) {
      $intervention->labels->clear();
      foreach ($this->runtime->support->resolveLabels($mutation->payload['labelIds'] ?? [], $organizationId) as $label) {
        $intervention->labels->add($label);
      }
    }
    $this->runtime->entityManager->flush();
    $this->recordStatusTransition($intervention, $aggregate, $mutation, $previousStatus, $nextStatus, $notifications);
    // A replan of a non-draft intervention leaves a trace: the operators who
    // planned around the old window learn it moved, and by how much.
    $nextPlannedStartAt = $aggregate->plannedStartAt();
    $nextDueAt = $aggregate->dueAt();
    $datesChanged = $previousPlannedStartAt?->getTimestamp() !== $nextPlannedStartAt?->getTimestamp()
      || $previousDueAt?->getTimestamp() !== $nextDueAt?->getTimestamp();
    if ('draft' !== $previousStatus && $datesChanged) {
      $this->runtime->activities->append(new InterventionActivityAppendRequest(
        $intervention->id,
        $organizationId,
        $this->runtime->memberPolicy->findMemberId($organizationId, $mutation->userId),
        new InterventionActivityContent('system', 'rescheduled', null, [
          'from' => [
            'plannedStartAt' => $previousPlannedStartAt?->format(DateTimeInterface::ATOM),
            'dueAt' => $previousDueAt?->format(DateTimeInterface::ATOM),
          ],
          'to' => [
            'plannedStartAt' => $nextPlannedStartAt?->format(DateTimeInterface::ATOM),
            'dueAt' => $nextDueAt?->format(DateTimeInterface::ATOM),
          ],
        ]),
      ));
    }
    $this->queueStatusSideEffects($intervention, $mutation, $previousStatus, $nextStatus, $notifications);

    return $this->runtime->views->interventionView($intervention);
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function resolveResponsibleId(InterventionAggregate $aggregate, array $payload, string $organizationId): ?string
  {
    if (!array_key_exists('responsibleId', $payload)) {
      return $aggregate->responsibleId();
    }
    $responsibleId = InterventionWorkflowPayload::nullableString($payload, 'responsibleId');
    if (null !== $responsibleId) {
      $this->runtime->memberPolicy->assertActiveMember($organizationId, $responsibleId);
    }

    return $responsibleId;
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return list<string>
   */
  private function resolveParticipants(InterventionAggregate $aggregate, array $payload, string $organizationId): array
  {
    if (!array_key_exists('participants', $payload)) {
      return $aggregate->participants();
    }
    $participants = InterventionWorkflowPayload::stringList($payload['participants']);
    $this->runtime->support->assertActiveMembers($organizationId, null, $participants);

    return $participants;
  }

  /**
   * @param array<string, mixed> $payload
   */
  private function resolveSiteId(InterventionAggregate $aggregate, array $payload, string $organizationId): ?string
  {
    if (!array_key_exists('siteId', $payload)) {
      return $aggregate->siteId();
    }
    $siteId = InterventionWorkflowPayload::nullableString($payload, 'siteId');
    $this->runtime->support->assertSiteBelongsToOrganization($siteId, $organizationId);

    return $siteId;
  }

  private function resolveNextStatus(InterventionAggregate $aggregate, InterventionWorkflowMutation $mutation, string $organizationId, string $previousStatus, ?string $responsibleId): ?InterventionStatus
  {
    if (!array_key_exists('status', $mutation->payload)) {
      return null;
    }
    $nextStatus = InterventionStatus::from(InterventionWorkflowPayload::requiredString($mutation->payload, 'status'));
    if (InterventionStatus::SUBMITTED === $nextStatus) {
      try {
        $this->runtime->memberPolicy->assertResponsible($organizationId, $mutation->userId, $responsibleId);
      } catch (InterventionConflictException $exception) {
        throw new InterventionAccessDeniedException($exception->getMessage(), previous: $exception);
      }
    }
    // Withdrawing a submission is reserved to the original responsible member;
    // planned and changes-requested transitions remain open to participants.
    if (InterventionStatus::IN_PROGRESS === $nextStatus && InterventionStatus::SUBMITTED->value === $previousStatus) {
      try {
        $this->runtime->memberPolicy->assertResponsible($organizationId, $mutation->userId, $aggregate->responsibleId(), 'withdraw');
      } catch (InterventionConflictException $exception) {
        throw new InterventionAccessDeniedException($exception->getMessage(), previous: $exception);
      }
    }

    return $nextStatus;
  }

  private function assertTaskPeriodsFit(InterventionRecord $intervention, InterventionAggregate $aggregate, string $organizationId): void
  {
    $timezone = $this->runtime->support->organizationTimezone($organizationId);
    $invalidPeriods = [];
    foreach ($intervention->workItems as $item) {
      if (!new WorkItemPeriod($item->workStartsOn, $item->workEndsOn)->fitsWithin(
        $aggregate->plannedStartAt()?->setTimezone($timezone)->format('Y-m-d'),
        $aggregate->dueAt()?->setTimezone($timezone)->format('Y-m-d'),
      )) {
        $invalidPeriods[] = $item->id;
      }
    }
    if ([] !== $invalidPeriods) {
      throw new InterventionValidationException('Replan these task periods before changing the intervention dates: ' . implode(', ', $invalidPeriods));
    }
  }

  /**
   * @param array<string, mixed> $payload
   * @param list<string> $participants
   */
  private function applyInterventionPatch(InterventionAggregate $aggregate, array $payload, ?string $siteId, ?string $responsibleId, array $participants, ?InterventionStatus $nextStatus): void
  {
    $aggregate->edit(
      policy: $this->transitionPolicy,
      patch: new InterventionPatch(
        text: new InterventionTextChanges(
          name: array_key_exists('name', $payload) ? InterventionWorkflowPayload::requiredString($payload, 'name') : null,
          description: array_key_exists('description', $payload) ? InterventionWorkflowPayload::nullableString($payload, 'description') : null,
          reviewNote: array_key_exists('reviewNote', $payload) ? InterventionWorkflowPayload::nullableString($payload, 'reviewNote') : null,
          hasName: array_key_exists('name', $payload),
          hasDescription: array_key_exists('description', $payload),
          hasReviewNote: array_key_exists('reviewNote', $payload),
        ),
        ownership: new InterventionOwnershipChanges(
          siteId: $siteId,
          responsibleId: $responsibleId,
          participants: $participants,
          hasSiteId: array_key_exists('siteId', $payload),
          hasResponsibleId: array_key_exists('responsibleId', $payload),
          hasParticipants: array_key_exists('participants', $payload),
        ),
        schedule: new InterventionScheduleChanges(
          priority: array_key_exists('priority', $payload) ? InterventionPriority::from(InterventionWorkflowPayload::requiredString($payload, 'priority')) : null,
          plannedStartAt: array_key_exists('plannedStartAt', $payload) ? InterventionWorkflowPayload::date($payload['plannedStartAt']) : null,
          dueAt: array_key_exists('dueAt', $payload) ? InterventionWorkflowPayload::date($payload['dueAt']) : null,
          hasPriority: array_key_exists('priority', $payload),
          hasPlannedStartAt: array_key_exists('plannedStartAt', $payload),
          hasDueAt: array_key_exists('dueAt', $payload),
        ),
        nextStatus: $nextStatus,
      ),
    );
  }

  /**
   * @param list<callable(): void> $notifications
   */
  private function recordStatusTransition(InterventionRecord $intervention, InterventionAggregate $aggregate, InterventionWorkflowMutation $mutation, string $previousStatus, ?InterventionStatus $nextStatus, array &$notifications): void
  {
    if (null === $nextStatus || $nextStatus->value === $previousStatus) {
      return;
    }
    $organizationId = $this->runtime->support->organizationId($intervention);
    $this->runtime->activities->append(new InterventionActivityAppendRequest(
      $intervention->id,
      $organizationId,
      $this->runtime->memberPolicy->findMemberId($organizationId, $mutation->userId),
      new InterventionActivityContent('system', 'status_changed', null, ['from' => $previousStatus, 'to' => $nextStatus->value]),
    ));
    // Dispatch only after the surrounding transaction commits; a rollback
    // must not leave a ledger event for a transition that never happened.
    $interventionId = $intervention->id;
    $interventionNumber = $intervention->number;
    $actorUserId = $mutation->userId;
    $fromStatus = $previousStatus;
    $toStatus = $nextStatus->value;
    $reviewNote = InterventionStatus::CHANGES_REQUESTED === $nextStatus ? $aggregate->reviewNote() : null;
    $notifications[] = fn () => $this->eventDispatcher->dispatch(new InterventionStatusTransitionedEvent(
      organizationId: $organizationId,
      interventionId: $interventionId,
      interventionNumber: $interventionNumber,
      actorUserId: $actorUserId,
      fromStatus: $fromStatus,
      toStatus: $toStatus,
      reviewNote: $reviewNote,
    ));
  }

  /**
   * @param list<callable(): void> $notifications
   */
  private function queueStatusSideEffects(InterventionRecord $intervention, InterventionWorkflowMutation $mutation, string $previousStatus, ?InterventionStatus $nextStatus, array &$notifications): void
  {
    if (InterventionStatus::CHANGES_REQUESTED === $nextStatus) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $responsibleId = $intervention->responsibleId;
      $notifications[] = fn () => $this->runtime->notifications->changesRequested($interventionId, $interventionName, $responsibleId);
    }
    // Every submission and resubmission tells reviewers a new round awaits.
    if (InterventionStatus::SUBMITTED === $nextStatus && InterventionStatus::SUBMITTED->value !== $previousStatus) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $organizationId = $this->runtime->support->organizationId($intervention);
      $actorUserId = $mutation->userId;
      $notifications[] = fn () => $this->runtime->notifications->submitted($interventionId, $interventionName, $organizationId, $actorUserId);
    }
    // Abandoned interventions cannot publish their draft resources.
    if (InterventionStatus::ABANDONED === $nextStatus) {
      $this->draftPublisher->discard($intervention->id);
    }
  }
}
