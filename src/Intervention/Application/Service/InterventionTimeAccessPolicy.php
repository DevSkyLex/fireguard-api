<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Time\TimeEntryTaskContext;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};

use function in_array;

/**
 * InterventionTimeAccessPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionTimeAccessPolicy
{
  /**
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization organization membership and permission checks
   * @param OrganizationWorkforceDirectoryPort $workforce organization-local membership and regional context directory
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private OrganizationWorkforceDirectoryPort $workforce)
  {
  }

  /**
   * Resolves the caller to an active member without revealing another organization's task.
   *
   * @since 1.0.0
   *
   * @param TimeEntryTaskContext $task task contribution or authorization context for this operation
   * @param string $userId authenticated account identifier used for authorization
   *
   * @return string active organization member identifier for the caller
   */
  public function actor(TimeEntryTaskContext $task, string $userId): string
  {
    if (!$this->authorization->isMemberOf($userId, $task->organizationId)) {
      throw InterventionNotFoundException::withId($task->taskId);
    }
    foreach ($this->workforce->members($task->organizationId) as $member) {
      if ($member->active && $member->userId === $userId) {
        return $member->id;
      }
    }

    throw InterventionNotFoundException::withId($task->taskId);
  }

  /**
   * Checks the dedicated permission to manage other members' time.
   *
   * @since 1.0.0
   *
   * @param TimeEntryTaskContext $task task contribution or authorization context for this operation
   * @param string $userId authenticated account identifier used for authorization
   *
   * @return bool whether management of other members' time is permitted
   */
  public function canManage(TimeEntryTaskContext $task, string $userId): bool
  {
    return $this->authorization->hasPermission($userId, $task->organizationId, 'organization.interventions.time.manage');
  }

  /**
   * Authorizes the beneficiary and contributor, including retained historical-assignee access.
   *
   * @since 1.0.0
   *
   * @param TimeEntryTaskContext $task task contribution or authorization context for this operation
   * @param string $userId authenticated account identifier used for authorization
   * @param string $beneficiary member whose performed work is being recorded
   * @param bool $hasExistingContribution whether the caller already owns a contribution on this task
   *
   * @return string authorized actor member identifier, distinct from the beneficiary when permitted
   */
  public function assertWrite(TimeEntryTaskContext $task, string $userId, string $beneficiary, bool $hasExistingContribution): string
  {
    $actor = $this->actor($task, $userId);
    $active = false;
    foreach ($this->workforce->members($task->organizationId) as $member) {
      if ($member->id === $beneficiary && $member->active) {
        $active = true;
      }
    }
    if (!$active) {
      throw InterventionNotFoundException::withId($beneficiary);
    }
    if ($this->canManage($task, $userId)) {
      return $actor;
    }
    if ($beneficiary !== $actor || !$this->authorization->hasPermission($userId, $task->organizationId, 'organization.interventions.time.write')) {
      throw new InterventionAccessDeniedException('Time entry permission is required.');
    }
    $contributor = $hasExistingContribution || $task->assigneeId === $actor || in_array($actor, $task->previousAssignees, true)
      || (null === $task->assigneeId && ($task->responsibleId === $actor || in_array($actor, $task->participants, true)));
    if (!$contributor) {
      throw new InterventionAccessDeniedException('Only authorized contributors may record time on this task.');
    }

    return $actor;
  }
}
