<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Adapter\Workflow;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Exception;
use InvalidArgumentException;
use Mission\Application\Contract\Resource\MissionListMetrics;
use Mission\Application\Contract\Workflow\{
  MissionWorkflowContext,
  MissionWorkflowMutation,
  MissionWorkflowPage,
  MissionWorkflowView
};
use Mission\Application\Port\Outbound\{MissionIssueQueryPort, MissionResourceGatewayPort, MissionWorkflowGatewayPort};
use Mission\Application\Service\{MissionIssueFinder, MissionMemberPolicy, MissionNotificationService};
use Mission\Domain\Exception\{
  MissionAccessDeniedException,
  MissionConflictException,
  MissionNotFoundException,
  MissionPreconditionFailedException,
  MissionValidationException
};
use Mission\Domain\Model\Mission\Mission as MissionAggregate;
use Mission\Domain\Service\{MissionChangePolicy, MissionTransitionPolicy};
use Mission\Domain\ValueObject\{MissionPriority, MissionResourceType, MissionStatus, MissionType};
use Mission\Infrastructure\Persistence\Doctrine\Mapper\MissionMapper;
use Mission\Infrastructure\Persistence\Doctrine\Record\{
  MissionChangeRecord,
  MissionRecord,
  MissionWorkItemRecord
};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function min;
use function sprintf;
use function trim;

/**
 * Gateway DoctrineMissionWorkflowGateway.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineMissionWorkflowGateway implements MissionIssueQueryPort, MissionWorkflowGatewayPort
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the DoctrineMissionWorkflowGateway class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param MissionTransitionPolicy $transitionPolicy the transition policy value
   * @param MissionChangePolicy $changePolicy the change policy value
   * @param MissionMemberPolicy $memberPolicy the member policy value
   * @param MissionNotificationService $notifications the notifications value
   * @param MissionResourceGatewayPort $resources the resources value
   * @param MissionIssueFinder $issueFinder the issue finder value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
    private MissionTransitionPolicy $transitionPolicy,
    private MissionChangePolicy $changePolicy,
    private MissionMemberPolicy $memberPolicy,
    private MissionNotificationService $notifications,
    private MissionResourceGatewayPort $resources,
    private MissionIssueFinder $issueFinder,
  ) {
  }

  /**
   * Method missionContext.
   *
   * Executes the mission context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionWorkflowContext the mission context result
   */
  public function missionContext(string $missionId): ?MissionWorkflowContext
  {
    $mission = $this->entityManager->find(MissionRecord::class, $missionId);

    return $mission instanceof MissionRecord ? $this->context($mission) : null;
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
   * @return ?MissionWorkflowContext the resource context result
   */
  public function resourceContext(string $resource, string $id): ?MissionWorkflowContext
  {
    if ('mission' === $resource) {
      return $this->missionContext($id);
    }
    $record = match ($resource) {
      'work_item' => $this->entityManager->find(MissionWorkItemRecord::class, $id),
      'change' => $this->entityManager->find(MissionChangeRecord::class, $id),
      default => null,
    };
    $mission = $record instanceof MissionWorkItemRecord || $record instanceof MissionChangeRecord
      ? $record->mission
      : null;

    return $mission instanceof MissionRecord ? $this->context($mission) : null;
  }

  /**
   * Method mutate.
   *
   * Executes the mutate operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   *
   * @return ?MissionWorkflowView the mutate result
   */
  public function mutate(MissionWorkflowMutation $mutation): ?MissionWorkflowView
  {
    /** @var list<callable(): void> $notifications */
    $notifications = [];
    $view = $this->entityManager->wrapInTransaction(
      function () use ($mutation, &$notifications): ?MissionWorkflowView {
        return match ($mutation->resource) {
          'mission' => $this->mutateMission($mutation, $notifications),
          'work_item' => $this->mutateWorkItem($mutation, $notifications),
          'change' => $this->mutateChange($mutation),
          default => throw new InvalidArgumentException('Unsupported mission workflow resource.'),
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
   * @return ?MissionWorkflowView the get result
   */
  public function get(string $resource, string $id): ?MissionWorkflowView
  {
    return match ($resource) {
      'mission' => ($record = $this->entityManager->find(MissionRecord::class, $id)) instanceof MissionRecord
        ? $this->missionView($record)
        : null,
      'work_item' => ($record = $this->entityManager->find(MissionWorkItemRecord::class, $id)) instanceof MissionWorkItemRecord
        ? $this->workItemView($record)
        : null,
      'change' => ($record = $this->entityManager->find(MissionChangeRecord::class, $id)) instanceof MissionChangeRecord
        ? $this->changeView($record)
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
   * @return MissionWorkflowPage the list result
   */
  public function list(string $resource, string $scopeId, array $filters, int $page, int $itemsPerPage): MissionWorkflowPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $qb = match ($resource) {
      'mission' => $this->missionListQuery($scopeId, $filters),
      'work_item' => $this->workItemListQuery($scopeId, $filters),
      'change' => $this->changeListQuery($scopeId, $filters),
      default => throw new InvalidArgumentException('Unsupported mission workflow resource.'),
    };
    $countQb = clone $qb;
    $alias = match ($resource) {
      'mission' => 'm',
      'work_item' => 'w',
      default => 'c',
    };
    $total = (int) $countQb->select('COUNT(' . $alias . '.id)')->getQuery()->getSingleScalarResult();
    /** @var list<MissionRecord|MissionWorkItemRecord|MissionChangeRecord> $records */
    $records = $qb
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();
    $metrics = 'mission' === $resource
      ? $this->resources->listMetrics(array_map(
        static fn (MissionRecord|MissionWorkItemRecord|MissionChangeRecord $record): string => $record->id,
        $records,
      ))
      : [];
    $items = array_map(function (object $record) use ($metrics): MissionWorkflowView {
      if ($record instanceof MissionRecord) {
        return $this->missionView($record, $metrics[$record->id] ?? null);
      }
      if ($record instanceof MissionWorkItemRecord) {
        return $this->workItemView($record);
      }

      return $this->changeView($record);
    }, $records);

    return new MissionWorkflowPage($items, $page, $itemsPerPage, $total);
  }

  /**
   * Method issues.
   *
   * Executes the issues operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return list<\Mission\Application\Contract\Resource\MissionIssue> the issues result
   */
  public function issues(string $missionId): array
  {
    return $this->issueFinder->find($missionId);
  }

  /**
   * Method mutateMission.
   *
   * Executes the mutate mission operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return ?MissionWorkflowView the mutate mission result
   */
  private function mutateMission(MissionWorkflowMutation $mutation, array &$notifications): ?MissionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createMission($mutation);
    }
    $mission = $this->mission($mutation->id);
    $this->assertRevision($mission->revision, $mutation->expectedRevision);
    if ('delete' === $mutation->action) {
      if (!in_array($mission->status, ['draft', 'abandoned'], true)) {
        throw new MissionConflictException('Only draft or abandoned missions can be deleted.');
      }
      $this->entityManager->remove($mission);
      $this->entityManager->flush();

      return null;
    }

    return $this->updateMission($mission, $mutation, $notifications);
  }

  /**
   * Method createMission.
   *
   * Executes the create mission operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   *
   * @return MissionWorkflowView the create mission result
   */
  private function createMission(MissionWorkflowMutation $mutation): MissionWorkflowView
  {
    $id = $mutation->id ?? $this->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->entityManager->find(MissionRecord::class, $id) instanceof MissionRecord) {
      throw new MissionPreconditionFailedException('The client UUID mission already exists.');
    }
    $organizationId = $this->requiredString($mutation->payload, 'organizationId');
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw MissionNotFoundException::withId($organizationId);
    }
    $responsibleId = $this->nullableString($mutation->payload, 'responsibleId');
    $participants = $this->stringList($mutation->payload['participants'] ?? []);
    $this->assertActiveMembers($organizationId, $responsibleId, $participants);
    $siteId = $this->nullableString($mutation->payload, 'siteId');
    $this->assertSiteBelongsToOrganization($siteId, $organizationId);
    $aggregate = MissionAggregate::create(
      id: $id,
      organizationId: $organizationId,
      type: MissionType::from($this->requiredString($mutation->payload, 'type')),
      name: $this->requiredString($mutation->payload, 'name'),
      referencePackId: $this->requiredString($mutation->payload, 'referencePackId'),
      siteId: $siteId,
      responsibleId: $responsibleId,
      participants: $participants,
      priority: MissionPriority::from($this->requiredString($mutation->payload, 'priority')),
      plannedStartAt: $this->date($mutation->payload['plannedStartAt'] ?? null),
      dueAt: $this->date($mutation->payload['dueAt'] ?? null),
    );
    $mission = MissionMapper::toRecord($aggregate);
    $mission->organization = $organization;
    $this->entityManager->persist($mission);
    $this->entityManager->flush();

    return $this->missionView($mission);
  }

  /**
   * Method updateMission.
   *
   * Executes the update mission operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   * @param MissionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return MissionWorkflowView the update mission result
   */
  private function updateMission(MissionRecord $mission, MissionWorkflowMutation $mutation, array &$notifications): MissionWorkflowView
  {
    $organizationId = $this->organizationId($mission);
    $aggregate = MissionMapper::toDomain($mission);
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
      $nextStatus = MissionStatus::from($this->requiredString($mutation->payload, 'status'));
      if (MissionStatus::SUBMITTED === $nextStatus) {
        try {
          $this->memberPolicy->assertResponsible($organizationId, $mutation->userId, $responsibleId);
        } catch (MissionConflictException $exception) {
          throw new MissionAccessDeniedException($exception->getMessage(), previous: $exception);
        }
      }
    }
    $aggregate->edit(
      policy: $this->transitionPolicy,
      name: array_key_exists('name', $mutation->payload) ? $this->requiredString($mutation->payload, 'name') : null,
      referencePackId: array_key_exists('referencePackId', $mutation->payload) ? $this->requiredString($mutation->payload, 'referencePackId') : null,
      siteId: $siteId,
      responsibleId: $responsibleId,
      participants: $participants,
      priority: array_key_exists('priority', $mutation->payload) ? MissionPriority::from($this->requiredString($mutation->payload, 'priority')) : null,
      plannedStartAt: array_key_exists('plannedStartAt', $mutation->payload) ? $this->date($mutation->payload['plannedStartAt']) : null,
      dueAt: array_key_exists('dueAt', $mutation->payload) ? $this->date($mutation->payload['dueAt']) : null,
      reviewNote: array_key_exists('reviewNote', $mutation->payload) ? $this->nullableString($mutation->payload, 'reviewNote') : null,
      nextStatus: $nextStatus,
      hasName: array_key_exists('name', $mutation->payload),
      hasReferencePackId: array_key_exists('referencePackId', $mutation->payload),
      hasSiteId: array_key_exists('siteId', $mutation->payload),
      hasResponsibleId: array_key_exists('responsibleId', $mutation->payload),
      hasParticipants: array_key_exists('participants', $mutation->payload),
      hasPriority: array_key_exists('priority', $mutation->payload),
      hasPlannedStartAt: array_key_exists('plannedStartAt', $mutation->payload),
      hasDueAt: array_key_exists('dueAt', $mutation->payload),
      hasReviewNote: array_key_exists('reviewNote', $mutation->payload),
    );
    MissionMapper::sync($aggregate, $mission);
    $this->entityManager->flush();
    if (MissionStatus::CHANGES_REQUESTED === $nextStatus) {
      $missionId = $mission->id;
      $missionName = $mission->name;
      $responsibleId = $mission->responsibleId;
      $notifications[] = fn () => $this->notifications->changesRequested($missionId, $missionName, $responsibleId);
    }

    return $this->missionView($mission);
  }

  /**
   * Method mutateWorkItem.
   *
   * Executes the mutate work item operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return ?MissionWorkflowView the mutate work item result
   */
  private function mutateWorkItem(MissionWorkflowMutation $mutation, array &$notifications): ?MissionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createWorkItem($mutation, $notifications);
    }
    $record = $this->workItem($mutation->id);
    $mission = $this->workItemMission($record);
    $this->assertRevision($record->revision, $mutation->expectedRevision);
    $this->assertMissionWorkMutable($mission);
    if ('draft' !== $mission->status) {
      $this->memberPolicy->assertCanExecuteWorkItem(
        $this->organizationId($mission),
        $mutation->userId,
        $mission->responsibleId,
        $mission->participants,
        $record->assigneeId,
      );
      if (array_key_exists('assigneeId', $mutation->payload)) {
        throw new MissionConflictException('Work item assignments are frozen after planning.');
      }
    }
    if ('delete' === $mutation->action) {
      if ('draft' !== $mission->status) {
        throw new MissionConflictException('Only prepared work items can be deleted.');
      }
      $this->entityManager->remove($record);
      $this->touch($mission, new DateTimeImmutable());
      $this->entityManager->flush();

      return null;
    }
    $previousAssigneeId = $record->assigneeId;
    if (array_key_exists('status', $mutation->payload)) {
      $status = $this->requiredString($mutation->payload, 'status');
      $skipReason = $this->nullableString($mutation->payload, 'skipReason');
      if ('skipped' === $status && (null === $skipReason || '' === trim($skipReason))) {
        throw new MissionValidationException('A skip reason is required.');
      }
      $record->status = $status;
      if ('planned' === $mission->status && 'planned' !== $status) {
        $mission->status = 'in_progress';
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
        $this->memberPolicy->assertActiveMember($this->organizationId($mission), $record->assigneeId);
      }
    }
    $now = new DateTimeImmutable();
    ++$record->revision;
    $record->updatedAt = $now;
    $this->touch($mission, $now);
    $this->entityManager->flush();
    if (null !== $record->assigneeId && $record->assigneeId !== $previousAssigneeId) {
      $missionId = $mission->id;
      $missionName = $mission->name;
      $assigneeId = $record->assigneeId;
      $notifications[] = fn () => $this->notifications->assigned($missionId, $missionName, $assigneeId);
    }

    return $this->workItemView($record);
  }

  /**
   * Method createWorkItem.
   *
   * Executes the create work item operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   * @param list<callable(): void> $notifications deferred notifications dispatched after commit
   *
   * @return MissionWorkflowView the create work item result
   */
  private function createWorkItem(MissionWorkflowMutation $mutation, array &$notifications): MissionWorkflowView
  {
    $id = $mutation->id ?? $this->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->entityManager->find(MissionWorkItemRecord::class, $id) instanceof MissionWorkItemRecord) {
      throw new MissionPreconditionFailedException('The client UUID work item already exists.');
    }
    $mission = $this->mission($this->requiredString($mutation->payload, 'missionId'));
    $this->assertMissionWorkMutable($mission);
    $source = $this->requiredString($mutation->payload, 'source');
    if ('draft' !== $mission->status && 'discovered' !== $source) {
      throw new MissionConflictException('Only discovered work items can be added after preparation.');
    }
    if ('draft' !== $mission->status) {
      $this->memberPolicy->assertCanExecuteWorkItem(
        $this->organizationId($mission),
        $mutation->userId,
        $mission->responsibleId,
        $mission->participants,
        null,
      );
    }
    $assigneeId = $this->nullableString($mutation->payload, 'assigneeId');
    if (null !== $assigneeId) {
      $this->memberPolicy->assertActiveMember($this->organizationId($mission), $assigneeId);
    }
    $now = new DateTimeImmutable();
    $record = new MissionWorkItemRecord();
    $record->id = $id;
    $record->mission = $mission;
    $record->action = $this->requiredString($mutation->payload, 'action');
    $record->target = $this->nullableString($mutation->payload, 'target');
    $record->resultResource = $this->nullableString($mutation->payload, 'resultResource');
    $record->assigneeId = $assigneeId;
    $record->source = $source;
    $record->required = (bool) ($mutation->payload['required'] ?? true);
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->touch($mission, $now);
    $this->entityManager->persist($record);
    $this->entityManager->flush();
    if (null !== $record->assigneeId) {
      $missionId = $mission->id;
      $missionName = $mission->name;
      $assigneeId = $record->assigneeId;
      $notifications[] = fn () => $this->notifications->assigned($missionId, $missionName, $assigneeId);
    }

    return $this->workItemView($record);
  }

  /**
   * Method mutateChange.
   *
   * Executes the mutate change operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   *
   * @return ?MissionWorkflowView the mutate change result
   */
  private function mutateChange(MissionWorkflowMutation $mutation): ?MissionWorkflowView
  {
    if ('create' === $mutation->action) {
      return $this->createChange($mutation);
    }
    $record = $this->change($mutation->id);
    $mission = $this->changeMission($record);
    $this->assertRevision($record->revision, $mutation->expectedRevision);
    $this->assertCanMutateChange($mission, $record->workItem, $mutation->userId);
    if ('delete' === $mutation->action) {
      $this->changePolicy->assertCanDelete(MissionStatus::from($mission->status));
      if ('proposed' !== $record->status) {
        throw new MissionConflictException('Only proposed mission changes can be deleted.');
      }
      $this->entityManager->remove($record);
      $this->touch($mission, new DateTimeImmutable());
      $this->entityManager->flush();

      return null;
    }
    if ([] === $mutation->payload) {
      return $this->changeView($record);
    }
    if (array_key_exists('patch', $mutation->payload)) {
      $this->changePolicy->assertCanEditPatch(MissionStatus::from($mission->status));
      if ('proposed' !== $record->status) {
        throw new MissionConflictException('Only proposed changes can be edited.');
      }
      $record->patch = $this->patch($mutation->payload['patch']);
    }
    if (array_key_exists('status', $mutation->payload)) {
      $status = $this->requiredString($mutation->payload, 'status');
      $this->changePolicy->assertCanChangeStatus(MissionStatus::from($mission->status), $status);
      $record->status = $status;
    }
    $now = new DateTimeImmutable();
    ++$record->revision;
    $record->updatedAt = $now;
    $this->touch($mission, $now);
    $this->entityManager->flush();

    return $this->changeView($record);
  }

  /**
   * Method createChange.
   *
   * Executes the create change operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowMutation $mutation the mutation value
   *
   * @return MissionWorkflowView the create change result
   */
  private function createChange(MissionWorkflowMutation $mutation): MissionWorkflowView
  {
    $id = $mutation->id ?? $this->uuidFactory->generateRaw();
    if ($mutation->createOnly && $this->entityManager->find(MissionChangeRecord::class, $id) instanceof MissionChangeRecord) {
      throw new MissionPreconditionFailedException('The client UUID mission change already exists.');
    }
    $mission = $this->mission($this->requiredString($mutation->payload, 'missionId'));
    $this->changePolicy->assertCanCreate(MissionStatus::from($mission->status));
    $workItemId = $this->nullableString($mutation->payload, 'workItemId');
    $workItem = null;
    if (null !== $workItemId) {
      $workItem = $this->entityManager->find(MissionWorkItemRecord::class, $workItemId);
      if (!$workItem instanceof MissionWorkItemRecord || $workItem->mission?->id !== $mission->id) {
        throw new MissionValidationException('Mission changes can only reference work items from the same mission.');
      }
    }
    $this->assertCanMutateChange($mission, $workItem, $mutation->userId);
    $now = new DateTimeImmutable();
    $record = new MissionChangeRecord();
    $record->id = $id;
    $record->mission = $mission;
    $record->workItem = $workItem;
    $record->resource = $this->requiredString($mutation->payload, 'resource');
    $record->patch = $this->patch($mutation->payload['patch'] ?? null);
    $record->createdAt = $now;
    $record->updatedAt = $now;
    $this->touch($mission, $now);
    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return $this->changeView($record);
  }

  /**
   * Method missionListQuery.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the mission list query result
   */
  private function missionListQuery(string $organizationId, array $filters): QueryBuilder
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('m')
      ->from(MissionRecord::class, 'm')
      ->where('m.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('m.updatedAt', 'DESC');
    foreach (['type', 'status'] as $filter) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        $qb->andWhere('m.' . $filter . ' = :' . $filter)->setParameter($filter, $filters[$filter]);
      }
    }
    if (is_string($filters['responsibleId'] ?? null) && '' !== $filters['responsibleId']) {
      $qb->andWhere('m.responsibleId = :responsibleId')->setParameter('responsibleId', $filters['responsibleId']);
    }
    if (is_string($filters['siteId'] ?? null) && '' !== $filters['siteId']) {
      $qb->andWhere('m.siteId = :siteId')->setParameter('siteId', $filters['siteId']);
    }
    if (is_string($filters['participantId'] ?? null) && '' !== $filters['participantId']) {
      $ids = $this->entityManager->getConnection()->fetchFirstColumn(
        'SELECT id FROM missions WHERE organization_id = :organization AND jsonb_exists(participants::jsonb, :participant)',
        ['organization' => $organizationId, 'participant' => $filters['participantId']],
      );
      $qb->andWhere([] === $ids ? '1 = 0' : 'm.id IN (:participantIds)');
      if ([] !== $ids) {
        $qb->setParameter('participantIds', $ids);
      }
    }
    foreach (['dueAtAfter' => '>=', 'dueAtBefore' => '<='] as $filter => $operator) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        try {
          $date = new DateTimeImmutable($filters[$filter]);
        } catch (Exception $exception) {
          throw new InvalidArgumentException(sprintf('The %s filter must be a valid date-time.', $filter), previous: $exception);
        }
        $qb->andWhere(sprintf('m.dueAt %s :%s', $operator, $filter))->setParameter($filter, $date);
      }
    }

    return $qb;
  }

  /**
   * Method workItemListQuery.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the work item list query result
   */
  private function workItemListQuery(string $missionId, array $filters): QueryBuilder
  {
    $mission = $this->entityManager->getReference(MissionRecord::class, $missionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('w')
      ->from(MissionWorkItemRecord::class, 'w')
      ->where('w.mission = :mission')
      ->setParameter('mission', $mission)
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
   * @param string $missionId the mission id value
   * @param array<string, mixed> $filters
   *
   * @return QueryBuilder the change list query result
   */
  private function changeListQuery(string $missionId, array $filters): QueryBuilder
  {
    $mission = $this->entityManager->getReference(MissionRecord::class, $missionId);
    $qb = $this->entityManager->createQueryBuilder()
      ->select('c')
      ->from(MissionChangeRecord::class, 'c')
      ->where('c.mission = :mission')
      ->setParameter('mission', $mission)
      ->orderBy('c.updatedAt', 'DESC');
    foreach (['resource', 'status'] as $filter) {
      if (is_string($filters[$filter] ?? null) && '' !== $filters[$filter]) {
        $qb->andWhere('c.' . $filter . ' = :' . $filter)->setParameter($filter, $filters[$filter]);
      }
    }

    return $qb;
  }

  /**
   * Method missionView.
   *
   * Executes the mission view operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   * @param ?MissionListMetrics $metrics preloaded collection metrics
   *
   * @return MissionWorkflowView the mission view result
   */
  private function missionView(MissionRecord $mission, ?MissionListMetrics $metrics = null): MissionWorkflowView
  {
    $organizationId = $this->organizationId($mission);
    if (null === $metrics) {
      $summary = $this->resources->summary($mission->id);
      $workItems = $this->resources->workItemSummary($mission->id);
      $issues = $this->issueFinder->find(
        $mission->id,
        $summary,
        $workItems,
        $this->resources->validationContext($mission->id),
      );
      $facilitiesCount = $summary->facilities;
      $equipmentCount = $summary->equipment;
      $inspectionsCount = $summary->inspections;
      $blockersCount = count(array_filter($issues, static fn ($issue): bool => 'blocker' === $issue->severity));
      $workItemsCount = $workItems->total;
      $completedWorkItemsCount = $workItems->completed;
      $proposedChangesCount = $this->entityManager->getRepository(MissionChangeRecord::class)->count([
        'mission' => $mission,
        'status' => 'proposed',
      ]);
    } else {
      $facilitiesCount = $metrics->facilities;
      $equipmentCount = $metrics->equipment;
      $inspectionsCount = $metrics->inspections;
      $blockersCount = $metrics->resourceBlockers;
      $blockersCount += 'site_setup' === $mission->type && 0 === $metrics->facilities ? 1 : 0;
      $blockersCount += in_array($mission->type, ['inventory', 'inspection_campaign'], true) && 0 === $metrics->workItems ? 1 : 0;
      $blockersCount += $metrics->requiredIncomplete > 0 ? 1 : 0;
      $workItemsCount = $metrics->workItems;
      $completedWorkItemsCount = $metrics->completedWorkItems;
      $proposedChangesCount = $metrics->proposedChanges;
    }

    return new MissionWorkflowView('mission', $organizationId, [
      'id' => $mission->id,
      'organization' => '/api/organizations/' . $organizationId,
      'type' => $mission->type,
      'name' => $mission->name,
      'status' => $mission->status,
      'referencePack' => '/api/reference-packs/' . $mission->referencePackId,
      'site' => null === $mission->siteId ? null : '/api/facilities/' . $mission->siteId,
      'responsible' => null === $mission->responsibleId ? null : '/api/organizations/' . $organizationId . '/members/' . $mission->responsibleId,
      'participants' => array_map(
        static fn (string $id): string => '/api/organizations/' . $organizationId . '/members/' . $id,
        $mission->participants,
      ),
      'priority' => $mission->priority,
      'plannedStartAt' => $mission->plannedStartAt?->format('c'),
      'dueAt' => $mission->dueAt?->format('c'),
      'reviewNote' => $mission->reviewNote,
      'revision' => $mission->revision,
      'facilitiesCount' => $facilitiesCount,
      'equipmentCount' => $equipmentCount,
      'inspectionsCount' => $inspectionsCount,
      'blockersCount' => $blockersCount,
      'workItemsCount' => $workItemsCount,
      'completedWorkItemsCount' => $completedWorkItemsCount,
      'proposedChangesCount' => $proposedChangesCount,
      'createdAt' => $mission->createdAt->format('c'),
      'updatedAt' => $mission->updatedAt->format('c'),
    ]);
  }

  /**
   * Method workItemView.
   *
   * Executes the work item view operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkItemRecord $record the record value
   *
   * @return MissionWorkflowView the work item view result
   */
  private function workItemView(MissionWorkItemRecord $record): MissionWorkflowView
  {
    $mission = $this->workItemMission($record, false);
    $organizationId = $this->organizationId($mission);

    return new MissionWorkflowView('work_item', $organizationId, [
      'id' => $record->id,
      'mission' => '/api/missions/' . $mission->id,
      'action' => $record->action,
      'target' => $record->target,
      'resultResource' => $record->resultResource,
      'assignee' => null === $record->assigneeId ? null : '/api/organizations/' . $organizationId . '/members/' . $record->assigneeId,
      'source' => $record->source,
      'status' => $record->status,
      'required' => $record->required,
      'skipReason' => $record->skipReason,
      'revision' => $record->revision,
      'createdAt' => $record->createdAt->format('c'),
      'updatedAt' => $record->updatedAt->format('c'),
    ]);
  }

  /**
   * Method changeView.
   *
   * Executes the change view operation.
   *
   * @since 1.0.0
   *
   * @param MissionChangeRecord $record the record value
   *
   * @return MissionWorkflowView the change view result
   */
  private function changeView(MissionChangeRecord $record): MissionWorkflowView
  {
    $mission = $this->changeMission($record, false);

    return new MissionWorkflowView('change', $this->organizationId($mission), [
      'id' => $record->id,
      'mission' => '/api/missions/' . $mission->id,
      'workItem' => null === $record->workItem ? null : '/api/mission-work-items/' . $record->workItem->id,
      'resource' => $record->resource,
      'patch' => $record->patch,
      'status' => $record->status,
      'revision' => $record->revision,
      'createdAt' => $record->createdAt->format('c'),
      'updatedAt' => $record->updatedAt->format('c'),
    ]);
  }

  /**
   * Method context.
   *
   * Executes the context operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   *
   * @return MissionWorkflowContext the context result
   */
  private function context(MissionRecord $mission): MissionWorkflowContext
  {
    return new MissionWorkflowContext(
      $mission->id,
      $this->organizationId($mission),
      $mission->status,
      $mission->responsibleId,
      $mission->participants,
    );
  }

  /**
   * Method mission.
   *
   * Executes the mission operation.
   *
   * @since 1.0.0
   *
   * @param ?string $id the id value
   *
   * @return MissionRecord the mission result
   */
  private function mission(?string $id): MissionRecord
  {
    $mission = null === $id
      ? null
      : $this->entityManager->find(MissionRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$mission instanceof MissionRecord) {
      throw MissionNotFoundException::withId($id ?? 'unknown');
    }

    return $mission;
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
   * @return MissionWorkItemRecord the work item result
   */
  private function workItem(?string $id): MissionWorkItemRecord
  {
    $record = null === $id
      ? null
      : $this->entityManager->find(MissionWorkItemRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof MissionWorkItemRecord) {
      throw MissionNotFoundException::withId($id ?? 'unknown');
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
   * @return MissionChangeRecord the change result
   */
  private function change(?string $id): MissionChangeRecord
  {
    $record = null === $id
      ? null
      : $this->entityManager->find(MissionChangeRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    if (!$record instanceof MissionChangeRecord) {
      throw MissionNotFoundException::withId($id ?? 'unknown');
    }

    return $record;
  }

  /**
   * Method workItemMission.
   *
   * Executes the work item mission operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkItemRecord $record the record value
   * @param bool $lock whether the mission must be write locked
   *
   * @return MissionRecord the work item mission result
   */
  private function workItemMission(MissionWorkItemRecord $record, bool $lock = true): MissionRecord
  {
    if (!$record->mission instanceof MissionRecord) {
      throw MissionNotFoundException::withId($record->id);
    }

    return $lock ? $this->mission($record->mission->id) : $record->mission;
  }

  /**
   * Method changeMission.
   *
   * Executes the change mission operation.
   *
   * @since 1.0.0
   *
   * @param MissionChangeRecord $record the record value
   * @param bool $lock whether the mission must be write locked
   *
   * @return MissionRecord the change mission result
   */
  private function changeMission(MissionChangeRecord $record, bool $lock = true): MissionRecord
  {
    if (!$record->mission instanceof MissionRecord) {
      throw MissionNotFoundException::withId($record->id);
    }

    return $lock ? $this->mission($record->mission->id) : $record->mission;
  }

  /**
   * Method organizationId.
   *
   * Executes the organization id operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   *
   * @return string the organization id result
   */
  private function organizationId(MissionRecord $mission): string
  {
    if (!$mission->organization instanceof OrganizationRecord) {
      throw new MissionConflictException('Mission organization is missing.');
    }

    return $mission->organization->id;
  }

  /**
   * Method assertMissionWorkMutable.
   *
   * Executes the assert mission work mutable operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   */
  private function assertMissionWorkMutable(MissionRecord $mission): void
  {
    if (in_array($mission->status, ['published', 'abandoned', 'submitted'], true)) {
      throw new MissionConflictException('Mission work is immutable in the current state.');
    }
  }

  /**
   * Method assertCanMutateChange.
   *
   * Ensures proposed changes created during execution are owned by the
   * assigned member or an active mission participant.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   * @param ?MissionWorkItemRecord $workItem the work item value
   * @param string $userId the current user id value
   */
  private function assertCanMutateChange(
    MissionRecord $mission,
    ?MissionWorkItemRecord $workItem,
    string $userId,
  ): void {
    if ('draft' === $mission->status || 'submitted' === $mission->status) {
      return;
    }
    $this->memberPolicy->assertCanExecuteWorkItem(
      $this->organizationId($mission),
      $userId,
      $mission->responsibleId,
      $mission->participants,
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
    if (null !== $expectedRevision && $revision !== $expectedRevision) {
      throw new MissionPreconditionFailedException('The resource revision is stale.');
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
        MissionResourceType::FACILITY,
        $siteId,
        $organizationId,
      )
    ) {
      throw new MissionValidationException('Mission site must belong to the mission organization.');
    }
  }

  /**
   * Method touch.
   *
   * Executes the touch operation.
   *
   * @since 1.0.0
   *
   * @param MissionRecord $mission the mission value
   * @param DateTimeImmutable $now the now value
   */
  private function touch(MissionRecord $mission, DateTimeImmutable $now): void
  {
    ++$mission->revision;
    $mission->updatedAt = $now;
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
