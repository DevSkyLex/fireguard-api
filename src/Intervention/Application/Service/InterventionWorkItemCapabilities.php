<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Port\Outbound\{InterventionTimeEntryRepositoryPort, InterventionWorkflowGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

use function in_array;

/**
 * InterventionWorkItemCapabilities.
 * Caller-specific capabilities for independent time and operational work.
 *
 * @category Service
 *
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
}
