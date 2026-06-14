<?php

declare(strict_types=1);

namespace Mission\Application\Port\Outbound;

use Mission\Application\Contract\Resource\{
  MissionAssignmentContext,
  MissionEquipmentDraft,
  MissionListMetrics,
  MissionResourceAssignment,
  MissionResourceSummary,
  MissionValidationContext,
  MissionWorkItemSummary
};
use Mission\Domain\ValueObject\MissionResourceType;

/**
 * Interface MissionResourceGatewayPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MissionResourceGatewayPort
{
  /**
   * Method missionAssignmentContext.
   *
   * Executes the mission assignment context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionAssignmentContext the mission assignment context result
   */
  public function missionAssignmentContext(string $missionId): ?MissionAssignmentContext;

  /**
   * Method missionMutationContext.
   *
   * Loads and locks a mission for a resource mutation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionAssignmentContext the mission mutation context result
   */
  public function missionMutationContext(string $missionId): ?MissionAssignmentContext;

  /**
   * Method validationContext.
   *
   * Executes the validation context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionValidationContext the validation context result
   */
  public function validationContext(string $missionId): ?MissionValidationContext;

  /**
   * Method resourceExists.
   *
   * Executes the resource exists operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   *
   * @return bool the resource exists result
   */
  public function resourceExists(MissionResourceType $type, string $resourceId): bool;

  /**
   * Method resourceBelongsToOrganization.
   *
   * Executes the resource belongs to organization operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   *
   * @return bool the resource belongs to organization result
   */
  public function resourceBelongsToOrganization(
    MissionResourceType $type,
    string $resourceId,
    string $organizationId,
  ): bool;

  /**
   * Method resourceInMissionScope.
   *
   * Determines whether a canonical resource is targeted by a mission work item.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the resource type
   * @param string $resourceId the resource id
   * @param string $missionId the mission id
   *
   * @return bool whether the resource belongs to the prepared mission scope
   */
  public function resourceInMissionScope(
    MissionResourceType $type,
    string $resourceId,
    string $missionId,
  ): bool;

  /**
   * Method clientIdExists.
   *
   * Executes the client id exists operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $clientId the client id value
   *
   * @return bool the client id exists result
   */
  public function clientIdExists(MissionResourceType $type, string $clientId): bool;

  /**
   * Method assign.
   *
   * Executes the assign operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param ?string $missionId the mission id value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the assign result
   */
  public function assign(
    MissionResourceType $type,
    string $resourceId,
    ?string $missionId,
    ?string $clientId,
  ): MissionResourceAssignment;

  /**
   * Method touchDraftMission.
   *
   * Executes the touch draft mission operation.
   *
   * @since 1.0.0
   *
   * @param ?string $missionId the mission id value
   */
  public function touchDraftMission(?string $missionId): void;

  /**
   * Method summary.
   *
   * Executes the summary operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return MissionResourceSummary the summary result
   */
  public function summary(string $missionId): MissionResourceSummary;

  /**
   * Method workItemSummary.
   *
   * Executes the work item summary operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return MissionWorkItemSummary the work item summary result
   */
  public function workItemSummary(string $missionId): MissionWorkItemSummary;

  /**
   * Method listMetrics.
   *
   * Aggregates the metrics required to render a mission collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   *
   * @return array<string, MissionListMetrics> metrics indexed by mission id
   */
  public function listMetrics(array $missionIds): array;

  /**
   * Method equipmentDrafts.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return list<MissionEquipmentDraft>
   */
  public function equipmentDrafts(string $missionId): array;
}
