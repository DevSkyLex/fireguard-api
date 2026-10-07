<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\ServiceRequest;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Intervention\Application\Contract\Activity\{InterventionActivityAppendRequest, InterventionActivityContent};
use Intervention\Application\Contract\Workflow\InterventionWorkflowMutation;
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionActivityRecord, InterventionRecord, InterventionWorkItemRecord};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use ServiceRequest\Application\Contract\Work\{ServiceRequestWorkLink, ServiceRequestWorkRequest};
use ServiceRequest\Application\Port\Outbound\ServiceRequestWorkPort;

use function count;
use function hash;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function json_decode;

/**
 * Class ServiceRequestWorkAdapter
 *
 * Links qualified requests to corrective work through the canonical workflow in the caller's main transaction.
 *
 * @category Adapter
 */
final readonly class ServiceRequestWorkAdapter implements ServiceRequestWorkPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies the owning manager, workflow, scoped equipment access and activity collaborators.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager owning main manager
   * @param InterventionWorkflowGatewayPort $workflow canonical intervention writer
   * @param OrganizationAuthorizationPort $authorization organization access decisions
   * @param EquipmentParkScopePort $equipment published equipment scope
   * @param InterventionActivityPort $activities immutable source linkage journal
   * @param InterventionMemberPolicy $members actor attribution within Intervention
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private InterventionWorkflowGatewayPort $workflow,
    private OrganizationAuthorizationPort $authorization,
    private EquipmentParkScopePort $equipment,
    private InterventionActivityPort $activities,
    private InterventionMemberPolicy $members,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method createOrLink
   *
   * Serializes source identity, preserves existing proofs and commits the linkage with the qualified request.
   *
   * @access public
   *
   * @param ServiceRequestWorkRequest $request immutable qualified request and explicit work selection
   *
   * @return ServiceRequestWorkLink durable corrective work identity
   *
   * @throws InterventionConflictException when transaction, selection or workflow state conflicts
   * @throws InterventionValidationException when no equipment has been qualified
   * @throws InterventionNotFoundException when the organization or equipment is outside scope
   * @throws InterventionAccessDeniedException when the actor lacks intervention planning or execution permission
   */
  public function createOrLink(ServiceRequestWorkRequest $request): ServiceRequestWorkLink
  {
    $this->reserveSelection($request);
    $key = $this->sourceKey($request);
    $existing = $this->linkage($request);
    if ($existing instanceof InterventionActivityRecord) {
      return $this->replay($existing, $request);
    }
    if (null === $request->equipmentId) {
      throw new InterventionValidationException('Qualify the equipment before creating corrective work.');
    }
    if ([] === $this->equipment->filterIds($request->organizationId, [$request->equipmentId], facilityId: $request->siteId)) {
      throw InterventionNotFoundException::withId($request->equipmentId);
    }
    $created = null === $request->existingInterventionId;
    $order = $created ? $this->createIntervention($request) : $this->existingIntervention($request);
    $taskId = $this->findTask($order, $request)->id ?? $this->createTask($order, $request);
    $this->activities->append(new InterventionActivityAppendRequest(
      $order->id,
      $request->organizationId,
      $this->members->assertActiveMemberForUser($request->organizationId, $request->actorId),
      new InterventionActivityContent('system', 'service_request_linked', null, [
        'requestId' => $request->requestId,
        'equipmentId' => $request->equipmentId,
        'taskId' => $taskId,
        'created' => $created,
        'selectedInterventionId' => $request->existingInterventionId,
        'selectedTaskId' => $request->existingTaskId,
      ]),
      $key,
    ));

    return new ServiceRequestWorkLink($order->id, $taskId, $created);
  }

  /**
   * Method reserveSelection
   *
   * Reserves source identity and an existing parent before the caller takes facility or equipment locks.
   * It records no work or activities and permits unchanged linkage replays after publication.
   *
   * @access public
   *
   * @param ServiceRequestWorkRequest $request immutable request and explicit intervention selection
   *
   * @return void no return value
   */
  public function reserveSelection(ServiceRequestWorkRequest $request): void
  {
    $decision = $this->authorization->resolveAccess($request->actorId, $request->organizationId, 'organization.interventions.plan');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::withId($request->requestId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Intervention planning permission is required to convert a service request.');
    }
    $connection = $this->entityManager->getConnection();
    if (!$connection->isTransactionActive()) {
      throw new InterventionConflictException('Service request conversion requires the main transaction.');
    }
    $key = $this->sourceKey($request);
    $connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:identity, 0))', ['identity' => $key]);
    $existing = $this->linkage($request);
    if ($existing instanceof InterventionActivityRecord) {
      $this->replay($existing, $request);

      return;
    }
    if (null !== $request->existingTaskId && null === $request->existingInterventionId) {
      throw new InterventionValidationException('Select the intervention owning the repair task.');
    }
    if (null !== $request->existingInterventionId) {
      $this->existingIntervention($request);
    }
  }

  /**
   * Method sourceKey
   *
   * Keeps one journal and transaction lock identity per qualified request across client operation retries.
   *
   * @access private
   *
   * @param ServiceRequestWorkRequest $request owning organization and stable request identity
   *
   * @return string unique source journal key
   */
  private function sourceKey(ServiceRequestWorkRequest $request): string
  {
    return hash('sha256', 'intervention.service_request:' . $request->organizationId . ':' . $request->requestId);
  }

  /**
   * Method linkage
   *
   * Reads the existing immutable linkage after its source identity has been reserved.
   *
   * @access private
   *
   * @param ServiceRequestWorkRequest $request owning organization and request identity
   *
   * @return ?InterventionActivityRecord persisted linkage when already converted
   */
  private function linkage(ServiceRequestWorkRequest $request): ?InterventionActivityRecord
  {
    return $this->entityManager->getRepository(InterventionActivityRecord::class)->findOneBy(['clientId' => $this->sourceKey($request), 'organizationId' => $request->organizationId]);
  }

  /**
   * Method replay
   *
   * Returns the original linkage even after closure without changing task facts or appending activities.
   *
   * @access private
   *
   * @param InterventionActivityRecord $activity persisted request linkage
   * @param ServiceRequestWorkRequest $request replayed selection
   *
   * @return ServiceRequestWorkLink original durable work identity
   */
  private function replay(InterventionActivityRecord $activity, ServiceRequestWorkRequest $request): ServiceRequestWorkLink
  {
    $payload = $activity->payload;
    if ('service_request_linked' !== $activity->event || !$activity->intervention instanceof InterventionRecord
      || !is_string($payload['taskId'] ?? null) || !is_bool($payload['created'] ?? null)
      || ($payload['requestId'] ?? null) !== $request->requestId || ($payload['equipmentId'] ?? null) !== $request->equipmentId
      || ($payload['selectedInterventionId'] ?? null) !== $request->existingInterventionId || ($payload['selectedTaskId'] ?? null) !== $request->existingTaskId) {
      throw new InterventionConflictException('The service request already identifies different corrective work.');
    }
    $task = $this->entityManager->find(InterventionWorkItemRecord::class, $payload['taskId']);
    if (!$task instanceof InterventionWorkItemRecord || $task->intervention?->id !== $activity->intervention->id) {
      throw new InterventionConflictException('The original corrective work is no longer available.');
    }

    return new ServiceRequestWorkLink($activity->intervention->id, $task->id, $payload['created']);
  }

  /**
   * Method createIntervention
   *
   * Creates a numbered draft using the same invariants and activity path as HTTP writes.
   *
   * @access private
   *
   * @param ServiceRequestWorkRequest $request qualified equipment and work content
   *
   * @return InterventionRecord freshly persisted corrective intervention
   */
  private function createIntervention(ServiceRequestWorkRequest $request): InterventionRecord
  {
    $view = $this->workflow->mutate(new InterventionWorkflowMutation('intervention', 'create', $request->actorId, null, [
      'organizationId' => $request->organizationId,
      'type' => 'corrective_maintenance',
      'name' => $request->title,
      'description' => $request->description,
      'priority' => 'normal',
      'siteId' => $request->siteId,
    ]));
    $id = $view?->data['id'] ?? null;
    $record = is_string($id) ? $this->entityManager->find(InterventionRecord::class, $id) : null;
    if (!$record instanceof InterventionRecord) {
      throw new InterventionConflictException('The workflow did not create corrective work.');
    }

    return $record;
  }

  /**
   * Method existingIntervention
   *
   * Locks and refreshes the parent before selecting or adding any task.
   *
   * @access private
   *
   * @param ServiceRequestWorkRequest $request explicit owning intervention selection
   *
   * @return InterventionRecord mutable corrective intervention within the same organization
   */
  private function existingIntervention(ServiceRequestWorkRequest $request): InterventionRecord
  {
    $record = $this->entityManager->find(InterventionRecord::class, $request->existingInterventionId);
    if (!$record instanceof InterventionRecord || $record->organization?->id !== $request->organizationId) {
      throw InterventionNotFoundException::withId($request->existingInterventionId ?? 'unknown');
    }
    $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);
    if ('corrective_maintenance' !== $record->type || in_array($record->status, ['submitted', 'published', 'abandoned'], true)) {
      throw new InterventionConflictException('Select an open corrective intervention for this repair.');
    }
    if (null !== $request->siteId && null !== $record->siteId && $request->siteId !== $record->siteId) {
      throw new InterventionConflictException('The selected intervention belongs to another site.');
    }

    return $record;
  }

  /**
   * Method findTask
   *
   * Resolves a real open repair without modifying its result, evidence, revision or assignment.
   *
   * @access private
   *
   * @param InterventionRecord $order locked intervention
   * @param ServiceRequestWorkRequest $request qualified equipment and optional task choice
   *
   * @return ?InterventionWorkItemRecord the selected repair, or null when a new task is required
   */
  private function findTask(InterventionRecord $order, ServiceRequestWorkRequest $request): ?InterventionWorkItemRecord
  {
    if (null !== $request->existingTaskId) {
      $task = $this->entityManager->find(InterventionWorkItemRecord::class, $request->existingTaskId);
      if (!$task instanceof InterventionWorkItemRecord || $task->intervention?->id !== $order->id) {
        throw InterventionNotFoundException::withId($request->existingTaskId);
      }
      $this->entityManager->refresh($task, LockMode::PESSIMISTIC_WRITE);
      if (!$this->isCompatibleTask($task, $request)) {
        throw new InterventionConflictException('Select an open repair task for the qualified equipment.');
      }

      return $task;
    }
    /** @var list<InterventionWorkItemRecord> $candidates */
    $candidates = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $order, 'action' => 'repair', 'status' => ['planned', 'in_progress']]);
    $matching = [];
    foreach ($candidates as $candidate) {
      $this->entityManager->refresh($candidate, LockMode::PESSIMISTIC_WRITE);
      if ($this->isCompatibleTask($candidate, $request)) {
        $matching[] = $candidate;
      }
    }
    if (count($matching) > 1) {
      throw new InterventionConflictException('Several open repairs match this equipment. Select the intended task.');
    }

    return $matching[0] ?? null;
  }

  /**
   * Method isCompatibleTask
   *
   * Supports canonical equipment targets and retained historical JSON targets without rewriting them.
   *
   * @access private
   *
   * @param InterventionWorkItemRecord $task candidate repair
   * @param ServiceRequestWorkRequest $request qualified equipment identity
   *
   * @return bool whether this task is still open for the same equipment
   */
  private function isCompatibleTask(InterventionWorkItemRecord $task, ServiceRequestWorkRequest $request): bool
  {
    $target = json_decode($task->target ?? '', true);
    $sameEquipment = $task->target === '/api/equipment/' . $request->equipmentId || (is_array($target) && ($target['equipmentId'] ?? null) === $request->equipmentId);

    return 'repair' === $task->action && in_array($task->status, ['planned', 'in_progress'], true) && $sameEquipment;
  }

  /**
   * Method createTask
   *
   * Adds prepared or discovered repair work through the existing execution and planning policy.
   *
   * @access private
   *
   * @param InterventionRecord $order owning mutable corrective intervention
   * @param ServiceRequestWorkRequest $request qualified equipment identity and actor
   *
   * @return string persisted repair task identifier
   */
  private function createTask(InterventionRecord $order, ServiceRequestWorkRequest $request): string
  {
    if ('draft' !== $order->status && !$this->authorization->hasPermission($request->actorId, $request->organizationId, 'organization.interventions.execute')) {
      throw new InterventionAccessDeniedException('Intervention execution permission is required to add discovered repair work.');
    }
    $view = $this->workflow->mutate(new InterventionWorkflowMutation('work_item', 'create', $request->actorId, null, [
      'interventionId' => $order->id,
      'action' => 'repair',
      'target' => '/api/equipment/' . $request->equipmentId,
      'source' => 'draft' === $order->status ? 'planned' : 'discovered',
      'required' => true,
    ]));
    $id = $view?->data['id'] ?? null;
    if (!is_string($id)) {
      throw new InterventionConflictException('The workflow did not create the repair task.');
    }

    return $id;
  }
  // #endregion
}
