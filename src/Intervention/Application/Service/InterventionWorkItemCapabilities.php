<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\Internal\InterventionTimeEntryRepositoryPort;
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};

use function array_key_exists;
use function in_array;
use function is_string;

/**
 * InterventionWorkItemCapabilities.
 * Caller-specific capabilities for independent time and operational work.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkItemCapabilities
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param InterventionTimeEntryRepositoryPort $timeEntries reads and persists the independent time journal
   * @param InterventionWorkflowGatewayPort $workflow reads intervention operational state through its owning boundary
   * @param InterventionTimeAccessPolicy $timeAccess checks current and historical contribution permissions
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   * @param ?OrganizationWorkforceDirectoryPort $workforce current organization workforce when available
   */
  public function __construct(
    private InterventionTimeEntryRepositoryPort $timeEntries,
    private InterventionWorkflowGatewayPort $workflow,
    private InterventionTimeAccessPolicy $timeAccess,
    private OrganizationAuthorizationPort $authorization,
    private ?OrganizationWorkforceDirectoryPort $workforce = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Derives independent time and operational actions from current membership and task state.
   *
   * @since 1.0.0
   *
   * @param string $taskId intervention work-item identifier
   * @param string $taskStatus current task execution status
   * @param string $userId authenticated account identifier used for authorization
   *
   * @return array{canLogTime: bool, canManageTime: bool, canReestimate: bool, canReassign: bool, canEditPlanning: bool, canExecute: bool}
   */
  public function forCaller(string $taskId, string $taskStatus, string $userId): array
  {
    $denied = ['canLogTime' => false, 'canManageTime' => false, 'canReestimate' => false, 'canReassign' => false, 'canEditPlanning' => false, 'canExecute' => false];
    $task = $this->timeEntries->context($taskId);
    if (null === $task) {
      return $denied;
    }

    try {
      $actor = $this->timeAccess->actor($task, $userId);
    } catch (InterventionNotFoundException) {
      return $denied;
    }
    $canLog = false;

    try {
      $this->timeAccess->assertWrite($task, $userId, $actor, false);
      $canLog = true;
    } catch (InterventionAccessDeniedException) {
      // Read access does not imply permission to contribute.
    }
    $context = $this->workflow->interventionContext($task->interventionId);
    $mutable = null !== $context && in_array($context->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)
      && !in_array($taskStatus, ['completed', 'skipped'], true);
    $canPlan = $mutable && $this->authorization->hasPermission($userId, $task->organizationId, 'organization.interventions.plan');
    $canExecute = $this->authorization->hasPermission($userId, $task->organizationId, 'organization.interventions.execute')
      && ($task->assigneeId === $actor || (null === $task->assigneeId && ($task->responsibleId === $actor || in_array($actor, $task->participants, true))));

    return ['canLogTime' => $canLog, 'canManageTime' => $this->timeAccess->canManage($task, $userId),
      'canReestimate' => $mutable && ('draft' === $context->status ? $canPlan : $canExecute),
      'canReassign' => $canPlan, 'canEditPlanning' => $canPlan,
      'canExecute' => $canExecute && null !== $context && in_array($context->status, ['planned', 'in_progress', 'changes_requested'], true)];
  }

  /** @param list<InterventionWorkflowView> $views
   * @return array<string, array{canLogTime: bool, canManageTime: bool, canReestimate: bool, canReassign: bool, canEditPlanning: bool, canExecute: bool}>
   */
  public function forViews(array $views, string $userId): array
  {
    [$ids, $statuses] = $this->taskIdentifiersAndStatuses($views);
    $tasks = $this->timeEntries->contexts($ids);
    $organizations = [];
    $parents = [];
    $results = [];
    foreach ($tasks as $id => $task) {
      if (!isset($organizations[$task->organizationId])) {
        $organizations[$task->organizationId] = $this->organizationAccess($task->organizationId, $userId);
      }
      if (!array_key_exists($task->interventionId, $parents)) {
        $parents[$task->interventionId] = $this->workflow->interventionContext($task->interventionId);
      }
      $results[$id] = $this->taskActions($task, $statuses[$id], $parents[$task->interventionId], $organizations[$task->organizationId]);
    }

    return $results;
  }

  /**
   * Method taskIdentifiersAndStatuses
   *
   * Retains input order for the batch and the last status for repeated task identifiers.
   *
   * @access private
   *
   * @param list<InterventionWorkflowView> $views authorized page
   *
   * @return array{list<string>, array<string, mixed>}
   */
  private function taskIdentifiersAndStatuses(array $views): array
  {
    $ids = [];
    $statuses = [];
    foreach ($views as $view) {
      $id = $view->data['id'] ?? null;
      if (is_string($id)) {
        $ids[] = $id;
        $statuses[$id] = $view->data['status'] ?? '';
      }
    }

    return [$ids, $statuses];
  }

  /**
   * Method organizationAccess
   *
   * Resolves one current actor and permission snapshot per organization in this page.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param string $userId authenticated account
   *
   * @return array{actor: ?string, permissions: array<string, bool>}
   */
  private function organizationAccess(string $organizationId, string $userId): array
  {
    $actor = $this->activeActor($organizationId, $userId);
    $permissions = [];
    foreach (['time.manage', 'time.write', 'plan', 'execute'] as $permission) {
      $permissions[$permission] = null !== $actor && $this->authorization->hasPermission($userId, $organizationId, 'organization.interventions.' . $permission);
    }

    return ['actor' => $actor, 'permissions' => $permissions];
  }

  /**
   * Method activeActor
   *
   * Requires current organization membership before selecting the first active workforce identity.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   * @param string $userId authenticated account
   *
   * @return ?string active member identifier
   */
  private function activeActor(string $organizationId, string $userId): ?string
  {
    if (!$this->authorization->isMemberOf($userId, $organizationId)) {
      return null;
    }
    foreach ($this->workforce?->members($organizationId) ?? [] as $member) {
      if ($member->active && $member->userId === $userId) {
        return $member->id;
      }
    }

    return null;
  }

  /**
   * Method taskActions
   *
   * Combines independent time rights with current assignment and parent phase.
   *
   * @access private
   *
   * @param TimeEntryTaskContext $task task contribution identities
   * @param mixed $status retained task status
   * @param ?InterventionWorkflowContext $context owning intervention phase
   * @param array{actor: ?string, permissions: array<string, bool>} $access current organization rights
   *
   * @return array{canLogTime: bool, canManageTime: bool, canReestimate: bool, canReassign: bool, canEditPlanning: bool, canExecute: bool}
   */
  private function taskActions(TimeEntryTaskContext $task, mixed $status, ?InterventionWorkflowContext $context, array $access): array
  {
    $actor = $access['actor'];
    $permissions = $access['permissions'];
    $assigned = $this->isCurrentContributor($task, $actor);
    $contributor = $assigned || (null !== $actor && in_array($actor, $task->previousAssignees, true));
    $mutable = $this->isMutable($status, $context);
    $canPlan = $mutable && $permissions['plan'];
    $canExecute = $assigned && $permissions['execute'];

    return ['canLogTime' => null !== $actor && ($permissions['time.manage'] || ($permissions['time.write'] && $contributor)),
      'canManageTime' => $permissions['time.manage'],
      'canReestimate' => $mutable && ('draft' === $context?->status ? $canPlan : $canExecute),
      'canReassign' => $canPlan, 'canEditPlanning' => $canPlan,
      'canExecute' => $canExecute && null !== $context && in_array($context->status, ['planned', 'in_progress', 'changes_requested'], true)];
  }

  /**
   * Method isCurrentContributor
   *
   * Unassigned tasks fall back to the responsible member and intervention participants.
   *
   * @access private
   *
   * @param TimeEntryTaskContext $task task contribution identities
   * @param ?string $actor current active member
   *
   * @return bool whether this actor can execute the task
   */
  private function isCurrentContributor(TimeEntryTaskContext $task, ?string $actor): bool
  {
    return null !== $actor && ($task->assigneeId === $actor
      || (null === $task->assigneeId && ($task->responsibleId === $actor || in_array($actor, $task->participants, true))));
  }

  /**
   * Method isMutable
   *
   * Planning changes require an editable parent and a nonterminal task.
   *
   * @access private
   *
   * @param mixed $status retained task status
   * @param ?InterventionWorkflowContext $context owning intervention phase
   *
   * @return bool whether planning can change
   */
  private function isMutable(mixed $status, ?InterventionWorkflowContext $context): bool
  {
    return null !== $context && in_array($context->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)
      && !in_array($status, ['completed', 'skipped'], true);
  }
  // #endregion
}
