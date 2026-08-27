<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Exception;
use Intervention\Application\Contract\Export\InterventionExportCandidate;
use Intervention\Application\Contract\Workflow\{
  InterventionWorkflowContext,
  InterventionWorkflowMutation,
  InterventionWorkflowPage,
  InterventionWorkflowView
};
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionIssueQueryPort, InterventionResourceGatewayPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\{InterventionDraftPublisher, InterventionIssueFinder, InterventionMemberPolicy, InterventionNotificationService};
use Intervention\Domain\Event\Workflow\InterventionStatusTransitionedEvent;
use Intervention\Domain\Exception\{
  InterventionAccessDeniedException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionPreconditionFailedException,
  InterventionPreconditionRequiredException,
  InterventionValidationException
};
use Intervention\Domain\Model\Intervention\Intervention as InterventionAggregate;
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionTransitionPolicy, InterventionWorkItemTransitionPolicy};
use Intervention\Domain\ValueObject\{InterventionChangeStatus, InterventionPriority, InterventionResourceType, InterventionStatus, InterventionType, InterventionWorkItemStatus};
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\{InterventionMapper, InterventionViewMapper};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionChangeRecord,
  InterventionLabelRecord,
  InterventionRecord,
  InterventionWorkItemRecord
};
use InvalidArgumentException;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function sprintf;
use function strtoupper;
use function trim;

/**
 * Gateway DoctrineInterventionWorkflowGatewayAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionWorkflowGatewayAdapter implements InterventionIssueQueryPort, InterventionWorkflowGatewayPort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DoctrineInterventionWorkflowGatewayAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param InterventionTransitionPolicy $transitionPolicy the transition policy value
   * @param InterventionChangePolicy $changePolicy the change policy value
   * @param InterventionWorkItemTransitionPolicy $workItemTransitionPolicy the work item transition policy value
   * @param InterventionMemberPolicy $memberPolicy the member policy value
   * @param InterventionNotificationService $notifications the notifications value
   * @param InterventionResourceGatewayPort $resources the resources value
   * @param InterventionIssueFinder $issueFinder the issue finder value
   * @param InterventionViewMapper $views the view mapper value
   * @param InterventionActivityPort $activities the activity feed port value
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher (audit ledger)
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
    private InterventionTransitionPolicy $transitionPolicy,
    private InterventionChangePolicy $changePolicy,
    private InterventionWorkItemTransitionPolicy $workItemTransitionPolicy,
    private InterventionMemberPolicy $memberPolicy,
    private InterventionNotificationService $notifications,
    private InterventionResourceGatewayPort $resources,
    private InterventionIssueFinder $issueFinder,
    private InterventionViewMapper $views,
    private InterventionActivityPort $activities,
    private InterventionDraftPublisher $draftPublisher,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }

  /**
   * Method interventionContext.
   *
   * Executes the intervention context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionWorkflowContext the intervention context result
   */
  public function interventionContext(string $interventionId): ?InterventionWorkflowContext
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);

    return $intervention instanceof InterventionRecord ? $this->context($intervention) : null;
  }

  /**
   * Method resourceContext.
   *
   * Executes the resource context operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $id the id value
   *
   * @return ?InterventionWorkflowContext the resource context result
   */
  public function resourceContext(string $resource, string $id): ?InterventionWorkflowContext
  {
    if ('intervention' === $resource) {
      return $this->interventionContext($id);
    }
    $record = match ($resource) {
      'work_item' => $this->entityManager->find(InterventionWorkItemRecord::class, $id),
      'change' => $this->entityManager->find(InterventionChangeRecord::class, $id),
      default => null,
    };
    $intervention = $record instanceof InterventionWorkItemRecord || $record instanceof InterventionChangeRecord
      ? $record->intervention
      : null;

    return $intervention instanceof InterventionRecord ? $this->context($intervention) : null;
  }

  /**
   * Method mutate.
   *
   * Executes the mutate operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   *
   * @return ?InterventionWorkflowView the mutate result
   */
  public function mutate(InterventionWorkflowMutation $mutation): ?InterventionWorkflowView
  {
    /** @var list<callable(): void> $notifications */
    $notifications = [];
    $view = $this->entityManager->wrapInTransaction(
      function () use ($mutation, &$notifications): ?InterventionWorkflowView {
        return match ($mutation->resource) {
          'intervention' => $this->mutateIntervention($mutation, $notifications),
          'work_item' => $this->mutateWorkItem($mutation, $notifications),
          'change' => $this->mutateChange($mutation),
          default => throw new InvalidArgumentException('Unsupported intervention workflow resource.'),
        };
      },
    );
    foreach ($notifications as $notify) {
      $notify();
    }

    return $view;
  }

  /**
   * Method get.
   *
   * Executes the get operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $id the id value
   *
   * @return ?InterventionWorkflowView the get result
   */
  public function get(string $resource, string $id): ?InterventionWorkflowView
  {
    return match ($resource) {
      'intervention' => ($record = $this->entityManager->find(InterventionRecord::class, $id)) instanceof InterventionRecord
        ? $this->views->interventionView($record)
        : null,
      'work_item' => ($record = $this->entityManager->find(InterventionWorkItemRecord::class, $id)) instanceof InterventionWorkItemRecord
        ? $this->views->workItemView($record)
        : null,
      'change' => ($record = $this->entityManager->find(InterventionChangeRecord::class, $id)) instanceof InterventionChangeRecord
        ? $this->views->changeView($record)
        : null,
      default => null,
    };
  }

  /**
   * Method list.
   *
   * Executes the list operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $scopeId the scope id value
   * @param array<string, mixed> $filters the filters value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return InterventionWorkflowPage the list result
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage, Sorting $sorting = new Sorting('updatedAt', SortDirection::DESC)): InterventionWorkflowPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $qb = match ($resource) {
      'intervention' => $this->interventionListQuery($scopeId, $filters),
      'work_item' => $this->workItemListQuery($scopeId, $filters),
      'change' => $this->changeListQuery($scopeId, $filters),
      default => throw new InvalidArgumentException('Unsupported intervention workflow resource.'),
    };
    $countQb = clone $qb;
    $alias = match ($resource) {
      'intervention' => 'm',
      'work_item' => 'w',
      default => 'c',
    };

    // Each list query ships a sensible default order; an explicit request
    // replaces it. Ordering was previously hard-wired, so `order[...]` reached
    // the provider and went nowhere — a paginated caller could not decide which
    // rows the first pages hold.
    if ('intervention' === $resource) {
      $qb->orderBy('m.' . $this->interventionSortField($sorting->field), strtoupper($sorting->direction->value))
        ->addOrderBy('m.id', 'ASC');
    }
    $total = (int) $countQb->resetDQLPart('orderBy')->select('COUNT(' . $alias . '.id)')->getQuery()->getSingleScalarResult();
    /** @var list<InterventionRecord|InterventionWorkItemRecord|InterventionChangeRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();
    $metrics = 'intervention' === $resource
      ? $this->resources->listMetrics(array_map(
        static fn (InterventionRecord|InterventionWorkItemRecord|InterventionChangeRecord $record): string => $record->id,
        $records,
      ))
      : [];
    $items = array_map(function (object $record) use ($metrics): InterventionWorkflowView {
      if ($record instanceof InterventionRecord) {
        return $this->views->interventionView($record, $metrics[$record->id] ?? null);
      }
      if ($record instanceof InterventionWorkItemRecord) {
        return $this->views->workItemView($record);
      }

      return $this->views->changeView($record);
    }, $records);

    return new InterventionWorkflowPage($items, $page, $itemsPerPage, $total);
  }

  /**
   * Method countInterventions.
   *
   * Executes the count interventions operation.
   *
   * @since 1.5.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters the filters value
   *
   * @return int the count interventions result
   */
  public function countInterventions(string $organizationId, array $filters): int
  {
    $qb = $this->interventionListQuery($organizationId, $filters);

    return (int) $qb->resetDQLPart('orderBy')->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
  }

  /**
   * Method listInterventionExportCandidates.
   *
   * Executes the list intervention export candidates operation.
   *
   * @since 1.5.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters the filters value
   *
   * @return list<InterventionExportCandidate> the list intervention export candidates result
   */
  public function listInterventionExportCandidates(string $organizationId, array $filters): array
  {
    $qb = $this->interventionListQuery($organizationId, $filters)
      ->orderBy('m.updatedAt', 'DESC')
      ->addOrderBy('m.id', 'ASC');

    /** @var list<InterventionRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (InterventionRecord $record): InterventionExportCandidate => new InterventionExportCandidate(
        id: $record->id,
        name: $record->name,
        type: $record->type,
        status: $record->status,
        priority: $record->priority,
        siteId: $record->siteId,
        responsibleId: $record->responsibleId,
        dueAt: $record->dueAt?->format(DateTimeInterface::ATOM),
        createdAt: $record->createdAt->format(DateTimeInterface::ATOM),
        updatedAt: $record->updatedAt->format(DateTimeInterface::ATOM),
      ),
      $records,
    );
  }

  /**
   * Method issues.
   *
   * Executes the issues operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return list<\Intervention\Application\Contract\Resource\InterventionIssue> the issues result
   */
  public function issues(string $interventionId): array
  {
    return $this->issueFinder->find($interventionId);
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
  private function mutateIntervention(InterventionWorkflowMutation $mutation, array &$notifications): ?InterventionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createIntervention($mutation);
    }
    $intervention = $this->intervention($mutation->id);
    $this->assertRevision($intervention->revision, $mutation->expectedRevision);
    if ('delete' === $mutation->action) {
      if (!in_array($intervention->status, ['draft', 'abandoned'], true)) {
        throw new InterventionConflictException('Only draft or abandoned interventions can be deleted.');
      }
      // Purge any still-draft resource records this intervention created before
      // removing it, so no orphaned drafts (and their unique client ids) survive.
      $this->draftPublisher->discard($intervention->id);
      $this->entityManager->remove($intervention);
      $this->entityManager->flush();

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
    $id = $mutation->id ?? $this->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->entityManager->find(InterventionRecord::class, $id) instanceof InterventionRecord) {
      throw new InterventionPreconditionFailedException('The client UUID intervention already exists.');
    }
    $organizationId = $this->requiredString($mutation->payload, 'organizationId');
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw InterventionNotFoundException::withId($organizationId);
    }
    $responsibleId = $this->nullableString($mutation->payload, 'responsibleId');
    $participants = $this->stringList($mutation->payload['participants'] ?? []);
    $this->assertActiveMembers($organizationId, $responsibleId, $participants);
    $siteId = $this->nullableString($mutation->payload, 'siteId');
    $this->assertSiteBelongsToOrganization($siteId, $organizationId);
    $aggregate = InterventionAggregate::create(
      id: $id,
      organizationId: $organizationId,
      type: InterventionType::from($this->requiredString($mutation->payload, 'type')),
      name: $this->requiredString($mutation->payload, 'name'),
      siteId: $siteId,
      responsibleId: $responsibleId,
      participants: $participants,
      priority: InterventionPriority::from($this->requiredString($mutation->payload, 'priority')),
      plannedStartAt: $this->date($mutation->payload['plannedStartAt'] ?? null),
      dueAt: $this->date($mutation->payload['dueAt'] ?? null),
      description: $this->nullableString($mutation->payload, 'description'),
    );
    $intervention = InterventionMapper::toRecord($aggregate);
    $intervention->organization = $organization;
    $intervention->number = $this->allocateNumber($organizationId);
    if (array_key_exists('labelIds', $mutation->payload)) {
      foreach ($this->resolveLabels($mutation->payload['labelIds'] ?? [], $organizationId) as $label) {
        $intervention->labels->add($label);
      }
    }
    $this->entityManager->persist($intervention);
    $this->entityManager->flush();
    $this->activities->append(
      $intervention->id,
      $organizationId,
      $this->memberPolicy->findMemberId($organizationId, $mutation->userId),
      'system',
      'created',
      null,
      null,
    );

    return $this->views->interventionView($intervention);
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
    $organizationId = $this->organizationId($intervention);
    $previousStatus = $intervention->status;
    $aggregate = InterventionMapper::toDomain($intervention);
    $previousPlannedStartAt = $aggregate->plannedStartAt();
    $previousDueAt = $aggregate->dueAt();
    $responsibleId = $aggregate->responsibleId();
    if (array_key_exists('responsibleId', $mutation->payload)) {
      $responsibleId = $this->nullableString($mutation->payload, 'responsibleId');
      if (null !== $responsibleId) {
        $this->memberPolicy->assertActiveMember($organizationId, $responsibleId);
      }
    }
    $participants = $aggregate->participants();
    if (array_key_exists('participants', $mutation->payload)) {
      $participants = $this->stringList($mutation->payload['participants']);
      $this->assertActiveMembers($organizationId, null, $participants);
    }
    $siteId = $aggregate->siteId();
    if (array_key_exists('siteId', $mutation->payload)) {
      $siteId = $this->nullableString($mutation->payload, 'siteId');
      $this->assertSiteBelongsToOrganization($siteId, $organizationId);
    }
    $nextStatus = null;
    if (array_key_exists('status', $mutation->payload)) {
      $nextStatus = InterventionStatus::from($this->requiredString($mutation->payload, 'status'));
      if (InterventionStatus::SUBMITTED === $nextStatus) {
        try {
          $this->memberPolicy->assertResponsible($organizationId, $mutation->userId, $responsibleId);
        } catch (InterventionConflictException $exception) {
          throw new InterventionAccessDeniedException($exception->getMessage(), previous: $exception);
        }
      }
      // Withdrawing a submission (submitted -> in_progress) is reserved to the
      // responsible member, like submitting. Gate on the source status so the
      // participant-open planned/changes_requested -> in_progress paths stay
      // untouched, and on the aggregate's responsible (the guard runs before
      // edit() applies any payload change).
      if (InterventionStatus::IN_PROGRESS === $nextStatus && InterventionStatus::SUBMITTED->value === $previousStatus) {
        try {
          $this->memberPolicy->assertResponsible($organizationId, $mutation->userId, $aggregate->responsibleId(), 'withdraw');
        } catch (InterventionConflictException $exception) {
          throw new InterventionAccessDeniedException($exception->getMessage(), previous: $exception);
        }
      }
    }
    $aggregate->edit(
      policy: $this->transitionPolicy,
      name: array_key_exists('name', $mutation->payload) ? $this->requiredString($mutation->payload, 'name') : null,
      description: array_key_exists('description', $mutation->payload) ? $this->nullableString($mutation->payload, 'description') : null,
      siteId: $siteId,
      responsibleId: $responsibleId,
      participants: $participants,
      priority: array_key_exists('priority', $mutation->payload) ? InterventionPriority::from($this->requiredString($mutation->payload, 'priority')) : null,
      plannedStartAt: array_key_exists('plannedStartAt', $mutation->payload) ? $this->date($mutation->payload['plannedStartAt']) : null,
      dueAt: array_key_exists('dueAt', $mutation->payload) ? $this->date($mutation->payload['dueAt']) : null,
      reviewNote: array_key_exists('reviewNote', $mutation->payload) ? $this->nullableString($mutation->payload, 'reviewNote') : null,
      nextStatus: $nextStatus,
      hasName: array_key_exists('name', $mutation->payload),
      hasDescription: array_key_exists('description', $mutation->payload),
      hasSiteId: array_key_exists('siteId', $mutation->payload),
      hasResponsibleId: array_key_exists('responsibleId', $mutation->payload),
      hasParticipants: array_key_exists('participants', $mutation->payload),
      hasPriority: array_key_exists('priority', $mutation->payload),
      hasPlannedStartAt: array_key_exists('plannedStartAt', $mutation->payload),
      hasDueAt: array_key_exists('dueAt', $mutation->payload),
      hasReviewNote: array_key_exists('reviewNote', $mutation->payload),
    );
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
      foreach ($this->resolveLabels($mutation->payload['labelIds'] ?? [], $organizationId) as $label) {
        $intervention->labels->add($label);
      }
    }
    $this->entityManager->flush();
    if (null !== $nextStatus && $nextStatus->value !== $previousStatus) {
      $this->activities->append(
        $intervention->id,
        $organizationId,
        $this->memberPolicy->findMemberId($organizationId, $mutation->userId),
        'system',
        'status_changed',
        null,
        ['from' => $previousStatus, 'to' => $nextStatus->value],
      );
      // Audit ledger: deferred like the notifications below, so the event
      // fires only once the surrounding wrapInTransaction has actually
      // committed — a rollback (e.g. a later validation failure in this same
      // request) must never leave a ledger entry for a transition that never
      // happened.
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
    // A replan of a non-draft intervention leaves a trace: the operators who
    // planned around the old window learn it moved, and by how much.
    $nextPlannedStartAt = $aggregate->plannedStartAt();
    $nextDueAt = $aggregate->dueAt();
    $datesChanged = $previousPlannedStartAt?->getTimestamp() !== $nextPlannedStartAt?->getTimestamp()
      || $previousDueAt?->getTimestamp() !== $nextDueAt?->getTimestamp();
    if ('draft' !== $previousStatus && $datesChanged) {
      $this->activities->append(
        $intervention->id,
        $organizationId,
        $this->memberPolicy->findMemberId($organizationId, $mutation->userId),
        'system',
        'rescheduled',
        null,
        [
          'from' => [
            'plannedStartAt' => $previousPlannedStartAt?->format(DateTimeInterface::ATOM),
            'dueAt' => $previousDueAt?->format(DateTimeInterface::ATOM),
          ],
          'to' => [
            'plannedStartAt' => $nextPlannedStartAt?->format(DateTimeInterface::ATOM),
            'dueAt' => $nextDueAt?->format(DateTimeInterface::ATOM),
          ],
        ],
      );
    }
    if (InterventionStatus::CHANGES_REQUESTED === $nextStatus) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $responsibleId = $intervention->responsibleId;
      $notifications[] = fn () => $this->notifications->changesRequested($interventionId, $interventionName, $responsibleId);
    }
    // Every entry into submitted — first submission and each resubmission —
    // tells the organization's reviewers a review round awaits them.
    if (InterventionStatus::SUBMITTED === $nextStatus && InterventionStatus::SUBMITTED->value !== $previousStatus) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $actorUserId = $mutation->userId;
      $notifications[] = fn () => $this->notifications->submitted($interventionId, $interventionName, $organizationId, $actorUserId);
    }
    // Abandoning an intervention is terminal: its draft resources can never be
    // published, so purge them here to avoid permanent orphaned draft rows.
    if (InterventionStatus::ABANDONED === $nextStatus) {
      $this->draftPublisher->discard($intervention->id);
    }

    return $this->views->interventionView($intervention);
  }

  /**
   * Method mutateWorkItem.
   *
   * Executes the mutate work item operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return ?InterventionWorkflowView the mutate work item result
   */
  private function mutateWorkItem(InterventionWorkflowMutation $mutation, array &$notifications): ?InterventionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createWorkItem($mutation, $notifications);
    }
    $record = $this->workItem($mutation->id);
    $intervention = $this->workItemIntervention($record);
    $this->assertRevision($record->revision, $mutation->expectedRevision);
    $this->assertInterventionWorkMutable($intervention);
    if ('draft' !== $intervention->status) {
      $this->memberPolicy->assertCanExecuteWorkItem(
        $this->organizationId($intervention),
        $mutation->userId,
        $intervention->responsibleId,
        $intervention->participants,
        $record->assigneeId,
      );
      if (array_key_exists('assigneeId', $mutation->payload)) {
        throw new InterventionConflictException('Work item assignments are frozen after planning.');
      }
    }
    if ('delete' === $mutation->action) {
      if ('draft' !== $intervention->status) {
        throw new InterventionConflictException('Only prepared work items can be deleted.');
      }
      $this->entityManager->remove($record);
      $this->touch($intervention, new DateTimeImmutable());
      $this->entityManager->flush();

      return null;
    }
    $previousAssigneeId = $record->assigneeId;
    $interventionAutoStarted = false;
    if (array_key_exists('status', $mutation->payload)) {
      $status = $this->requiredString($mutation->payload, 'status');
      $skipReason = $this->nullableString($mutation->payload, 'skipReason');
      $nextWorkItemStatus = InterventionWorkItemStatus::from($status);
      $this->workItemTransitionPolicy->assertAllowed(InterventionWorkItemStatus::from($record->status), $nextWorkItemStatus, $skipReason);
      $record->status = $nextWorkItemStatus->value;
      if ('planned' === $intervention->status && 'planned' !== $status) {
        $intervention->status = 'in_progress';
        $interventionAutoStarted = true;
      }
    }
    if (array_key_exists('skipReason', $mutation->payload)) {
      $skipReason = $this->nullableString($mutation->payload, 'skipReason');
      $record->skipReason = null === $skipReason ? null : trim($skipReason);
    }
    if (array_key_exists('resultResource', $mutation->payload)) {
      $record->resultResource = $this->nullableString($mutation->payload, 'resultResource');
    }
    if (array_key_exists('assigneeId', $mutation->payload)) {
      $record->assigneeId = $this->nullableString($mutation->payload, 'assigneeId');
      if (null !== $record->assigneeId) {
        $this->memberPolicy->assertActiveMember($this->organizationId($intervention), $record->assigneeId);
      }
    }
    $now = new DateTimeImmutable();
    ++$record->revision;
    $record->updatedAt = $now;
    $this->touch($intervention, $now);
    $this->entityManager->flush();
    // Starting work on any item auto-advances the intervention
    // planned -> in_progress; journal it as a `status_changed` activity so the
    // audit feed reflects the lifecycle change (RNCP traceability), mirroring
    // the explicit intervention status-transition path.
    if ($interventionAutoStarted) {
      $activityOrganizationId = $this->organizationId($intervention);
      $this->activities->append(
        $intervention->id,
        $activityOrganizationId,
        $this->memberPolicy->findMemberId($activityOrganizationId, $mutation->userId),
        'system',
        'status_changed',
        null,
        ['from' => 'planned', 'to' => 'in_progress'],
      );
      // Audit ledger: same deferred-until-commit treatment as the explicit
      // transition path in updateIntervention().
      $autoStartInterventionId = $intervention->id;
      $autoStartInterventionNumber = $intervention->number;
      $autoStartActorUserId = $mutation->userId;
      $notifications[] = fn () => $this->eventDispatcher->dispatch(new InterventionStatusTransitionedEvent(
        organizationId: $activityOrganizationId,
        interventionId: $autoStartInterventionId,
        interventionNumber: $autoStartInterventionNumber,
        actorUserId: $autoStartActorUserId,
        fromStatus: 'planned',
        toStatus: 'in_progress',
      ));
    }
    if (null !== $record->assigneeId && $record->assigneeId !== $previousAssigneeId) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $assigneeId = $record->assigneeId;
      $notifications[] = fn () => $this->notifications->assigned($interventionId, $interventionName, $assigneeId);
    }

    return $this->views->workItemView($record);
  }

  /**
   * Method createWorkItem.
   *
   * Executes the create work item operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return InterventionWorkflowView the create work item result
   */
  private function createWorkItem(InterventionWorkflowMutation $mutation, array &$notifications): InterventionWorkflowView
  {
    $id = $mutation->id ?? $this->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->entityManager->find(InterventionWorkItemRecord::class, $id) instanceof InterventionWorkItemRecord) {
      throw new InterventionPreconditionFailedException('The client UUID work item already exists.');
    }
    $intervention = $this->intervention($this->requiredString($mutation->payload, 'interventionId'));
    $this->assertInterventionWorkMutable($intervention);
    $source = $this->requiredString($mutation->payload, 'source');
    if ('draft' !== $intervention->status && 'discovered' !== $source) {
      throw new InterventionConflictException('Only discovered work items can be added after preparation.');
    }
    if ('draft' !== $intervention->status) {
      $this->memberPolicy->assertCanExecuteWorkItem(
        $this->organizationId($intervention),
        $mutation->userId,
        $intervention->responsibleId,
        $intervention->participants,
        null,
      );
    }
    $assigneeId = $this->nullableString($mutation->payload, 'assigneeId');
    if (null !== $assigneeId) {
      $this->memberPolicy->assertActiveMember($this->organizationId($intervention), $assigneeId);
    }
    $now = new DateTimeImmutable();
    $record = new InterventionWorkItemRecord();
    $record->id = $id;
    $record->intervention = $intervention;
    $record->action = $this->requiredString($mutation->payload, 'action');
    $record->target = $this->nullableString($mutation->payload, 'target');
    $record->resultResource = $this->nullableString($mutation->payload, 'resultResource');
    $record->assigneeId = $assigneeId;
    $record->source = $source;
    $record->required = (bool) ($mutation->payload['required'] ?? true);
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->touch($intervention, $now);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    if (null !== $record->assigneeId) {
      $interventionId = $intervention->id;
      $interventionName = $intervention->name;
      $assigneeId = $record->assigneeId;
      $notifications[] = fn () => $this->notifications->assigned($interventionId, $interventionName, $assigneeId);
    }

    return $this->views->workItemView($record);
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
  private function mutateChange(InterventionWorkflowMutation $mutation): ?InterventionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createChange($mutation);
    }
    $record = $this->change($mutation->id);
    $intervention = $this->changeIntervention($record);
    $this->assertRevision($record->revision, $mutation->expectedRevision);
    $this->assertCanMutateChange($intervention, $record->workItem, $mutation->userId);
    if ('delete' === $mutation->action) {
      $this->changePolicy->assertCanDelete(InterventionStatus::from($intervention->status));
      if ('proposed' !== $record->status) {
        throw new InterventionConflictException('Only proposed intervention changes can be deleted.');
      }
      $this->entityManager->remove($record);
      $this->touch($intervention, new DateTimeImmutable());
      $this->entityManager->flush();

      return null;
    }
    if ([] === $mutation->payload) {
      return $this->views->changeView($record);
    }
    if (array_key_exists('patch', $mutation->payload)) {
      $this->changePolicy->assertCanEditPatch(InterventionStatus::from($intervention->status));
      if ('proposed' !== $record->status) {
        throw new InterventionConflictException('Only proposed changes can be edited.');
      }
      $record->patch = $this->patch($mutation->payload['patch']);
    }
    if (array_key_exists('status', $mutation->payload)) {
      $status = $this->requiredString($mutation->payload, 'status');
      $nextChangeStatus = InterventionChangeStatus::from($status);
      $this->changePolicy->assertCanChangeStatus(InterventionStatus::from($intervention->status), $nextChangeStatus);
      $this->changePolicy->assertTransitionAllowed(InterventionChangeStatus::from($record->status), $nextChangeStatus);
      $record->status = $nextChangeStatus->value;
    }
    $now = new DateTimeImmutable();
    ++$record->revision;
    $record->updatedAt = $now;
    $this->touch($intervention, $now);
    $this->entityManager->flush();

    return $this->views->changeView($record);
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
    $id = $mutation->id ?? $this->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->entityManager->find(InterventionChangeRecord::class, $id) instanceof InterventionChangeRecord) {
      throw new InterventionPreconditionFailedException('The client UUID intervention change already exists.');
    }
    $intervention = $this->intervention($this->requiredString($mutation->payload, 'interventionId'));
    $this->changePolicy->assertCanCreate(InterventionStatus::from($intervention->status));
    $workItemId = $this->nullableString($mutation->payload, 'workItemId');
    $workItem = null;
    if (null !== $workItemId) {
      $workItem = $this->entityManager->find(InterventionWorkItemRecord::class, $workItemId);
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
    $record->resource = $this->requiredString($mutation->payload, 'resource');
    $record->patch = $this->patch($mutation->payload['patch'] ?? null);
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->touch($intervention, $now);
    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->views->changeView($record);
  }

  /**
   * Method filterValues.
   *
   * @static
   *
   * Normalizes an enum/IRI list filter that accepts one or several values: a
   * plain string becomes a one-element list, a list keeps its non-empty
   * string members. The provider already validates and normalizes; this keeps
   * the gateway safe against a caller passing the legacy scalar form.
   *
   * @since 1.4.0
   *
   * @param mixed $raw the raw filter value
   *
   * @return list<string> the normalized values
   */
  private static function filterValues(mixed $raw): array
  {
    if (is_string($raw) && '' !== $raw) {
      return [$raw];
    }
    if (!is_array($raw)) {
      return [];
    }

    return array_values(array_filter(
      array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $raw),
      static fn (string $value): bool => '' !== $value,
    ));
  }

  /**
   * Method interventionSortField.
   *
   * Maps a requested sort field to a column, falling back to `updatedAt` so an
   * unknown field can never reach DQL.
   *
   * @since 1.0.0
   *
   * @param string $field the requested sort field
   *
   * @return string the record property to order by
   */
  private function interventionSortField(string $field): string
  {
    return match ($field) {
      'name', 'status', 'type', 'priority', 'plannedStartAt', 'dueAt', 'createdAt' => $field,
      default => 'updatedAt',
    };
  }

  /**
   * Method interventionListQuery.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the intervention list query result
   */
  private function interventionListQuery(string $organizationId, array $filters): QueryBuilder
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(InterventionRecord::class, 'm')
      ->where('m.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('m.updatedAt', 'DESC');
    foreach (['type', 'status', 'priority', 'responsibleId', 'siteId'] as $filter) {
      $values = self::filterValues($filters[$filter] ?? null);
      if ([] !== $values) {
        $qb->andWhere('m.' . $filter . ' IN (:' . $filter . ')')->setParameter($filter, $values);
      }
    }
    if (is_string($filters['name'] ?? null)) {
      TrigramSearchExpression::apply($qb, 'name', $filters['name'], 'm.name');
    }
    if (is_int($filters['number'] ?? null)) {
      $qb->andWhere('m.number = :number')->setParameter('number', $filters['number']);
    }
    $labelIds = self::filterValues($filters['labelId'] ?? null);
    if ([] !== $labelIds) {
      $qb->innerJoin('m.labels', 'l')
        ->andWhere('l.id IN (:labelIds)')
        ->setParameter('labelIds', $labelIds);
    }
    if (is_string($filters['participantId'] ?? null) && '' !== $filters['participantId']) {
      $ids = $this->entityManager->getConnection()->fetchFirstColumn(
        'SELECT id FROM interventions WHERE organization_id = :organization AND jsonb_exists(participants::jsonb, :participant)',
        ['organization' => $organizationId, 'participant' => $filters['participantId']],
      );
      $qb->andWhere([] === $ids ? '1 = 0' : 'm.id IN (:participantIds)');
      if ([] !== $ids) {
        $qb->setParameter('participantIds', $ids);
      }
    }
    if (is_string($filters['memberId'] ?? null) && '' !== $filters['memberId']) {
      $ids = $this->entityManager->getConnection()->fetchFirstColumn(
        'SELECT id FROM interventions WHERE organization_id = :organization AND jsonb_exists(participants::jsonb, :member)',
        ['organization' => $organizationId, 'member' => $filters['memberId']],
      );
      $qb->andWhere(
        [] === $ids
          ? 'm.responsibleId = :memberId'
          : '(m.responsibleId = :memberId OR m.id IN (:memberInterventionIds))',
      )->setParameter('memberId', $filters['memberId']);
      if ([] !== $ids) {
        $qb->setParameter('memberInterventionIds', $ids);
      }
    }
    foreach (
      [
        'dueAtAfter' => ['dueAt', '>='],
        'dueAtBefore' => ['dueAt', '<='],
        'plannedStartAtAfter' => ['plannedStartAt', '>='],
        'plannedStartAtBefore' => ['plannedStartAt', '<='],
      ] as $filter => [$field, $operator]
    ) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        try {
          $date = new DateTimeImmutable($filters[$filter]);
        } catch (Exception $exception) {
          throw new InvalidArgumentException(sprintf('The %s filter must be a valid date-time.', $filter), previous: $exception);
        }
        $qb->andWhere(sprintf('m.%s %s :%s', $field, $operator, $filter))->setParameter($filter, $date);
      }
    }
    // `overdueAsOf`/`overdueExcludedStatuses` are never client input: the
    // handler derives them from the `due=overdue` filter (see
    // `ListInterventionWorkflowHandler::resolveFilters()`) and this query
    // only applies them mechanically, exactly like the `dueAtAfter`/
    // `dueAtBefore` bounds above, with which they compose.
    $overdueAsOf = $filters['overdueAsOf'] ?? null;
    if ($overdueAsOf instanceof DateTimeImmutable) {
      $qb->andWhere('m.dueAt < :overdueAsOf')->setParameter('overdueAsOf', $overdueAsOf);
    }
    $overdueExcludedStatuses = $filters['overdueExcludedStatuses'] ?? null;
    if (is_array($overdueExcludedStatuses) && [] !== $overdueExcludedStatuses) {
      $qb->andWhere('m.status NOT IN (:overdueExcludedStatuses)')->setParameter('overdueExcludedStatuses', $overdueExcludedStatuses);
    }

    return $qb;
  }

  /**
   * Method workItemListQuery.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the work item list query result
   */
  private function workItemListQuery(string $interventionId, array $filters): QueryBuilder
  {
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('w')
      ->from(InterventionWorkItemRecord::class, 'w')
      ->where('w.intervention = :intervention')
      ->setParameter('intervention', $intervention)
      ->orderBy('w.updatedAt', 'DESC');
    foreach (['source', 'action', 'status', 'assigneeId'] as $filter) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        $qb->andWhere('w.' . $filter . ' = :' . $filter)->setParameter($filter, $filters[$filter]);
      }
    }

    return $qb;
  }

  /**
   * Method changeListQuery.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the change list query result
   */
  private function changeListQuery(string $interventionId, array $filters): QueryBuilder
  {
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('c')
      ->from(InterventionChangeRecord::class, 'c')
      ->where('c.intervention = :intervention')
      ->setParameter('intervention', $intervention)
      ->orderBy('c.updatedAt', 'DESC');
    foreach (['resource', 'status'] as $filter) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        $qb->andWhere('c.' . $filter . ' = :' . $filter)->setParameter($filter, $filters[$filter]);
      }
    }

    return $qb;
  }

  /**
   * Method context.
   *
   * Executes the context operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   *
   * @return InterventionWorkflowContext the context result
   */
  private function context(InterventionRecord $intervention): InterventionWorkflowContext
  {
    return new InterventionWorkflowContext(
      $intervention->id,
      $this->organizationId($intervention),
      $intervention->status,
      $intervention->responsibleId,
      $intervention->participants,
    );
  }

  /**
   * Method intervention.
   *
   * Executes the intervention operation.
   *
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return InterventionRecord the intervention result
   */
  private function intervention(?string $id): InterventionRecord
  {
    $intervention = null === $id
      ? null
      : $this->entityManager->find(InterventionRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($id ?? 'unknown');
    }

    return $intervention;
  }

  /**
   * Method workItem.
   *
   * Executes the work item operation.
   *
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return InterventionWorkItemRecord the work item result
   */
  private function workItem(?string $id): InterventionWorkItemRecord
  {
    $record = null === $id
      ? null
      : $this->entityManager->find(InterventionWorkItemRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof InterventionWorkItemRecord) {
      throw InterventionNotFoundException::withId($id ?? 'unknown');
    }

    return $record;
  }

  /**
   * Method change.
   *
   * Executes the change operation.
   *
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return InterventionChangeRecord the change result
   */
  private function change(?string $id): InterventionChangeRecord
  {
    $record = null === $id
      ? null
      : $this->entityManager->find(InterventionChangeRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof InterventionChangeRecord) {
      throw InterventionNotFoundException::withId($id ?? 'unknown');
    }

    return $record;
  }

  /**
   * Method workItemIntervention.
   *
   * Executes the work item intervention operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkItemRecord $record the record value
   * @param bool $lock whether the intervention must be write locked
   *
   * @return InterventionRecord the work item intervention result
   */
  private function workItemIntervention(InterventionWorkItemRecord $record, bool $lock = true): InterventionRecord
  {
    if (!$record->intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($record->id);
    }

    return $lock ? $this->intervention($record->intervention->id) : $record->intervention;
  }

  /**
   * Method changeIntervention.
   *
   * Executes the change intervention operation.
   *
   * @since 1.0.0
   *
   * @param InterventionChangeRecord $record the record value
   * @param bool $lock whether the intervention must be write locked
   *
   * @return InterventionRecord the change intervention result
   */
  private function changeIntervention(InterventionChangeRecord $record, bool $lock = true): InterventionRecord
  {
    if (!$record->intervention instanceof InterventionRecord) {
      throw InterventionNotFoundException::withId($record->id);
    }

    return $lock ? $this->intervention($record->intervention->id) : $record->intervention;
  }

  /**
   * Method organizationId.
   *
   * Executes the organization id operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   *
   * @return string the organization id result
   */
  private function organizationId(InterventionRecord $intervention): string
  {
    if (!$intervention->organization instanceof OrganizationRecord) {
      throw new InterventionConflictException('Intervention organization is missing.');
    }

    return $intervention->organization->id;
  }

  /**
   * Method assertInterventionWorkMutable.
   *
   * Executes the assert intervention work mutable operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   */
  private function assertInterventionWorkMutable(InterventionRecord $intervention): void
  {
    if (in_array($intervention->status, ['published', 'abandoned', 'submitted'], true)) {
      throw new InterventionConflictException('Intervention work is immutable in the current state.');
    }
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
    $this->memberPolicy->assertCanExecuteWorkItem(
      $this->organizationId($intervention),
      $userId,
      $intervention->responsibleId,
      $intervention->participants,
      $workItem?->assigneeId,
    );
  }

  /**
   * Method assertRevision.
   *
   * Executes the assert revision operation.
   *
   * @since 1.0.0
   *
   * @param int $revision the revision value
   * @param ?int $expectedRevision the expected revision value
   */
  private function assertRevision(int $revision, ?int $expectedRevision): void
  {
    if (null === $expectedRevision) {
      throw new InterventionPreconditionRequiredException('If-Match is required to mutate an existing resource.');
    }
    if ($revision !== $expectedRevision) {
      throw new InterventionPreconditionFailedException('The resource revision is stale.');
    }
  }

  /**
   * Method assertActiveMembers.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants
   */
  private function assertActiveMembers(string $organizationId, ?string $responsibleId, array $participants): void
  {
    if (null !== $responsibleId) {
      $this->memberPolicy->assertActiveMember($organizationId, $responsibleId);
    }
    foreach ($participants as $participantId) {
      $this->memberPolicy->assertActiveMember($organizationId, $participantId);
    }
  }

  /**
   * Method assertSiteBelongsToOrganization.
   *
   * Executes the assert site belongs to organization operation.
   *
   * @since 1.0.0
   *
   * @param ?string $siteId the site id value
   * @param string $organizationId the organization id value
   */
  private function assertSiteBelongsToOrganization(?string $siteId, string $organizationId): void
  {
    if (
      null !== $siteId
      && !$this->resources->resourceBelongsToOrganization(
        InterventionResourceType::FACILITY,
        $siteId,
        $organizationId,
      )
    ) {
      throw new InterventionValidationException('Intervention site must belong to the intervention organization.');
    }
  }

  /**
   * Method resolveLabels.
   *
   * Resolves and asserts a list of label ids belong to the intervention's
   * organization. Labels are record-level metadata, resolved directly against
   * `InterventionLabelRecord` rather than through a port, since the gateway
   * already queries records directly (e.g. `assertSiteBelongsToOrganization`).
   *
   * @since 1.0.0
   *
   * @param mixed $labelIds the raw label ids value
   * @param string $organizationId the organization id value
   *
   * @return list<InterventionLabelRecord>
   */
  private function resolveLabels(mixed $labelIds, string $organizationId): array
  {
    $labels = [];
    foreach ($this->stringList($labelIds) as $labelId) {
      $label = $this->entityManager->find(InterventionLabelRecord::class, $labelId);
      if (!$label instanceof InterventionLabelRecord || $label->organization?->id !== $organizationId) {
        throw new InterventionValidationException('Intervention labels must belong to the intervention organization.');
      }
      $labels[] = $label;
    }

    return $labels;
  }

  /**
   * Method touch.
   *
   * Executes the touch operation.
   *
   * @since 1.0.0
   *
   * @param InterventionRecord $intervention the intervention value
   * @param DateTimeImmutable $now the now value
   */
  private function touch(InterventionRecord $intervention, DateTimeImmutable $now): void
  {
    ++$intervention->revision;
    $intervention->updatedAt = $now;
  }

  /**
   * Method allocateNumber.
   *
   * Atomically allocates the next per-organization intervention number through
   * an upsert with `RETURNING`, so concurrent creations serialize on the
   * counter row instead of racing a `MAX()+1` read before the new intervention
   * row exists. Runs inside the surrounding mutation transaction.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   *
   * @return int the allocated intervention number
   */
  private function allocateNumber(string $organizationId): int
  {
    $number = $this->entityManager->getConnection()->fetchOne(
      'INSERT INTO intervention_number_counters (organization_id, last_number) VALUES (:organization, 1) '
      . 'ON CONFLICT (organization_id) DO UPDATE SET last_number = intervention_number_counters.last_number + 1 '
      . 'RETURNING last_number',
      ['organization' => $organizationId],
    );
    if (!is_numeric($number)) {
      throw new InterventionConflictException('Failed to allocate an intervention number.');
    }

    return (int) $number;
  }

  /**
   * Method requiredString.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload
   * @param string $key the key value
   *
   * @return string the required string result
   */
  private function requiredString(array $payload, string $key): string
  {
    $value = $payload[$key] ?? null;
    if (!is_string($value) || '' === $value) {
      throw new InvalidArgumentException($key . ' must be a non-empty string.');
    }

    return $value;
  }

  /**
   * Method nullableString.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $payload
   * @param string $key the key value
   *
   * @return ?string the nullable string result
   */
  private function nullableString(array $payload, string $key): ?string
  {
    $value = $payload[$key] ?? null;
    if (null === $value) {
      return null;
    }
    if (!is_string($value)) {
      throw new InvalidArgumentException($key . ' must be a string or null.');
    }

    return $value;
  }

  /**
   * Method stringList.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value value
   *
   * @return list<string>
   */
  private function stringList(mixed $value): array
  {
    if (!is_array($value)) {
      throw new InvalidArgumentException('Expected a list of strings.');
    }

    return array_values(array_unique(array_map(
      static function (mixed $item): string {
        if (!is_string($item)) {
          throw new InvalidArgumentException('Expected a list of strings.');
        }

        return $item;
      },
      $value,
    )));
  }

  /**
   * Method date.
   *
   * Executes the date operation.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value value
   *
   * @return ?DateTimeImmutable the date result
   */
  private function date(mixed $value): ?DateTimeImmutable
  {
    if (null === $value) {
      return null;
    }
    if (!is_string($value)) {
      throw new InvalidArgumentException('Expected a date-time string or null.');
    }

    return new DateTimeImmutable($value);
  }

  /**
   * Method patch.
   *
   * @since 1.0.0
   *
   * @param mixed $value the value value
   *
   * @return array<string, mixed>
   */
  private function patch(mixed $value): array
  {
    if (!is_array($value)) {
      throw new InvalidArgumentException('Patch must be an object.');
    }
    foreach (array_keys($value) as $key) {
      if (!is_string($key)) {
        throw new InvalidArgumentException('Patch must be an object.');
      }
    }

    /** @var array<string, mixed> $value */
    return $value;
  }
}
