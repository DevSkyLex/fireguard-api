<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

use Mission\Application\Contract\Resource\MissionResourceAssignment;
use Mission\Domain\ValueObject\MissionResourceType;

/**
 * Interface MissionResourceOwnerPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionResourceOwnerPort
{
  /**
   * Method supportsResourceType.
   *
   * Executes the supports resource type operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   *
   * @return bool the supports resource type result
   */
  public function supportsResourceType(MissionResourceType $type): bool;

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
   * @param ?string $missionId the mission id value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the assign result
   */
  public function assign(string $resourceId, ?string $missionId, ?string $clientId): MissionResourceAssignment;

  /**
   * Method countForMission.
   *
   * Executes the count for mission operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return int the count for mission result
   */
  public function countForMission(string $missionId): int;

  /**
   * Method countsForMissions.
   *
   * Aggregates owned resource counts for a mission collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   *
   * @return array<string, int> counts indexed by mission id
   */
  public function countsForMissions(array $missionIds): array;

  /**
   * Method blockerCountsForMissions.
   *
   * Aggregates resource-specific blocker counts for a mission collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   *
   * @return array<string, int> blocker counts indexed by mission id
   */
  public function blockerCountsForMissions(array $missionIds): array;
}
