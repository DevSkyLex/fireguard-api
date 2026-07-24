<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Domain\ValueObject\InterventionResourceType;

/**
 * Interface InterventionResourceOwnerPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionResourceOwnerPort
{
  /**
   * Method supportsResourceType.
   *
   * Executes the supports resource type operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   *
   * @return bool the supports resource type result
   */
  public function supportsResourceType(InterventionResourceType $type): bool;

  /**
   * Method resourceExists.
   *
   * Executes the resource exists operation.
   *
   * @since 1.0.0
   *
   * @param string $resourceId the resource id value
   *
   * @return bool the resource exists result
   */
  public function resourceExists(string $resourceId): bool;

  /**
   * Method resourceBelongsToOrganization.
   *
   * Executes the resource belongs to organization operation.
   *
   * @since 1.0.0
   *
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   *
   * @return bool the resource belongs to organization result
   */
  public function resourceBelongsToOrganization(string $resourceId, string $organizationId): bool;

  /**
   * Method clientIdExists.
   *
   * Executes the client id exists operation.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client id value
   *
   * @return bool the client id exists result
   */
  public function clientIdExists(string $clientId): bool;

  /**
   * Method assign.
   *
   * Executes the assign operation.
   *
   * @since 1.0.0
   *
   * @param string $resourceId the resource id value
   * @param ?string $interventionId the intervention id value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the assign result
   */
  public function assign(string $resourceId, ?string $interventionId, ?string $clientId): InterventionResourceAssignment;

  /**
   * Method countForIntervention.
   *
   * Executes the count for intervention operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return int the count for intervention result
   */
  public function countForIntervention(string $interventionId): int;

  /**
   * Method countsForInterventions.
   *
   * Aggregates owned resource counts for an intervention collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   *
   * @return array<string, int> counts indexed by intervention id
   */
  public function countsForInterventions(array $interventionIds): array;

  /**
   * Method blockerCountsForInterventions.
   *
   * Aggregates resource-specific blocker counts for an intervention collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   *
   * @return array<string, int> blocker counts indexed by intervention id
   */
  public function blockerCountsForInterventions(array $interventionIds): array;
}
