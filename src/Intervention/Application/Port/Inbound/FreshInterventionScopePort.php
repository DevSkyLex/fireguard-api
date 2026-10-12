<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Resource\InterventionAssignmentContext;

/** Current owner facts and participant policies usable before reserving an HTTP receipt. @category Port */
interface FreshInterventionScopePort
{
  public function organizationId(string $interventionId): ?string;

  public function context(string $interventionId): ?InterventionAssignmentContext;

  public function mutationPermission(string $interventionId, string $actorId, string $organizationId): string;

  public function assertExecutionAccess(string $organizationId, string $interventionId, string $actorId, ?string $workItemId = null): void;

  public function assertResponsibleAccess(string $organizationId, string $interventionId, string $actorId): void;

  /**
   * The caller supplies only MAC-verified original assignment evidence for a now-deleted work item.
   */
  public function assertRetainedWorkItemExecutionAccess(string $organizationId, string $interventionId, string $actorId, ?string $originalAssigneeId): void;

  /**
   * @param list<string> $originalParticipants MAC-verified original team scope for a now-deleted parent
   */
  public function assertRetainedInterventionExecutionAccess(string $organizationId, string $actorId, ?string $originalResponsibleId, array $originalParticipants, ?string $originalAssigneeId = null): void;

  public function assertInventoryAccess(string $organizationId, string $interventionId, ?string $workItemId, ?string $equipmentId, string $actorId): void;
}
