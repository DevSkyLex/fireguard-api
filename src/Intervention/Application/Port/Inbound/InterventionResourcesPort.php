<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Resource\{InterventionAssignmentContext, InterventionResourceAssignment, InterventionResourceType};

/** Owner-published intervention resource capability.
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionResourcesPort
{
  public function attach(InterventionResourceType $type, string $resourceId, string $organizationId, ?string $interventionId, ?string $clientId = null): InterventionResourceAssignment;

  public function assertOfflineCreate(InterventionResourceType $type, ?string $clientId, bool $forUpdate = true): void;

  public function interventionContext(string $interventionId): ?InterventionAssignmentContext;

  public function resourceInInterventionScope(InterventionResourceType $type, string $resourceId, string $interventionId): bool;

  public function workItemBelongsToIntervention(string $workItemId, string $interventionId): bool;

  /**
   * Reads the current policy without locking when $forUpdate is false; commands recheck it under their owning transaction.
   */
  public function mutationPermission(string $interventionId, ?string $userId = null, ?string $expectedOrganizationId = null, bool $forUpdate = true): string;

  public function touchDraftIntervention(?string $interventionId): void;
}
