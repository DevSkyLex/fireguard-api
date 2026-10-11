<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
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
  /**
   * @since 1.0.0
   *
   * @param InterventionTimeEntryRepositoryPort $timeEntries reads and persists the independent time journal
   * @param InterventionWorkflowGatewayPort $workflow reads intervention operational state through its owning boundary
   * @param InterventionTimeAccessPolicy $timeAccess checks current and historical contribution permissions
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   */
  public function __construct(
    private InterventionTimeEntryRepositoryPort $timeEntries,
    private InterventionWorkflowGatewayPort $workflow,
    private InterventionTimeAccessPolicy $timeAccess,
    private OrganizationAuthorizationPort $authorization,
    private ?OrganizationWorkforceDirectoryPort $workforce = null,
  ) {
  }

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
    $ids = [];
    $statuses = [];
    foreach ($views as $view) {
      $id = $view->data['id'] ?? null;
      if (is_string($id)) {
        $ids[] = $id;
        $statuses[$id] = $view->data['status'] ?? '';
      }
    }
    $tasks = $this->timeEntries->contexts($ids);
    $organizations = [];
    $parents = [];
    $results = [];
    foreach ($tasks as $id => $task) {
      if (!isset($organizations[$task->organizationId])) {
        $actor = null;
        if ($this->authorization->isMemberOf($userId, $task->organizationId)) {
          foreach ($this->workforce?->members($task->organizationId) ?? [] as $member) {
            if ($member->active && $member->userId === $userId) {
              $actor = $member->id;

              break;
            }
          }
        }
        $permissions = [];
        foreach (['time.manage', 'time.write', 'plan', 'execute'] as $permission) {
          $permissions[$permission] = null !== $actor && $this->authorization->hasPermission($userId, $task->organizationId, 'organization.interventions.' . $permission);
        }
        $organizations[$task->organizationId] = ['actor' => $actor, 'permissions' => $permissions];
      }
      if (!array_key_exists($task->interventionId, $parents)) {
        $parents[$task->interventionId] = $this->workflow->interventionContext($task->interventionId);
      }
      $actor = $organizations[$task->organizationId]['actor'];
      $permissions = $organizations[$task->organizationId]['permissions'];
      $context = $parents[$task->interventionId];
      $contributor = null !== $actor && ($task->assigneeId === $actor || in_array($actor, $task->previousAssignees, true)
        || (null === $task->assigneeId && ($task->responsibleId === $actor || in_array($actor, $task->participants, true))));
      $mutable = null !== $context && in_array($context->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true) && !in_array($statuses[$id], ['completed', 'skipped'], true);
      $canPlan = $mutable && $permissions['plan'];
      $canExecute = null !== $actor && $permissions['execute'] && ($task->assigneeId === $actor
        || (null === $task->assigneeId && ($task->responsibleId === $actor || in_array($actor, $task->participants, true))));
      $results[$id] = ['canLogTime' => null !== $actor && ($permissions['time.manage'] || ($permissions['time.write'] && $contributor)),
        'canManageTime' => $permissions['time.manage'],
        'canReestimate' => $mutable && ('draft' === $context->status ? $canPlan : $canExecute),
        'canReassign' => $canPlan, 'canEditPlanning' => $canPlan,
        'canExecute' => $canExecute && null !== $context && in_array($context->status, ['planned', 'in_progress', 'changes_requested'], true)];
    }

    return $results;
  }
}
