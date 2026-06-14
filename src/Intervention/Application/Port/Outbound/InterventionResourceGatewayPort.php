<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Resource\{
  InterventionAssignmentContext,
  InterventionEquipmentDraft,
  InterventionListMetrics,
  InterventionResourceAssignment,
  InterventionResourceSummary,
  InterventionValidationContext,
  InterventionWorkItemSummary
};
use Intervention\Domain\ValueObject\InterventionResourceType;

/**
 * Interface InterventionResourceGatewayPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionResourceGatewayPort
{
  /**
   * Method interventionAssignmentContext.
   *
   * Executes the intervention assignment context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionAssignmentContext the intervention assignment context result
   */
  public function interventionAssignmentContext(string $interventionId): ?InterventionAssignmentContext;

  /**
   * Method interventionMutationContext.
   *
   * Loads and locks a intervention for a resource mutation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionAssignmentContext the intervention mutation context result
   */
  public function interventionMutationContext(string $interventionId): ?InterventionAssignmentContext;

  /**
   * Method validationContext.
   *
   * Executes the validation context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionValidationContext the validation context result
   */
  public function validationContext(string $interventionId): ?InterventionValidationContext;

  /**
   * Method resourceExists.
   *
   * Executes the resource exists operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   *
   * @return bool the resource exists result
   */
  public function resourceExists(InterventionResourceType $type, string $resourceId): bool;

  /**
   * Method resourceBelongsToOrganization.
   *
   * Executes the resource belongs to organization operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   *
   * @return bool the resource belongs to organization result
   */
  public function resourceBelongsToOrganization(
    InterventionResourceType $type,
    string $resourceId,
    string $organizationId,
  ): bool;

  /**
   * Method resourceInInterventionScope.
   *
   * Determines whether a canonical resource is targeted by a intervention work item.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the resource type
   * @param string $resourceId the resource id
   * @param string $interventionId the intervention id
   *
   * @return bool whether the resource belongs to the prepared intervention scope
   */
  public function resourceInInterventionScope(
    InterventionResourceType $type,
    string $resourceId,
    string $interventionId,
  ): bool;

  /**
   * Method clientIdExists.
   *
   * Executes the client id exists operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $clientId the client id value
   *
   * @return bool the client id exists result
   */
  public function clientIdExists(InterventionResourceType $type, string $clientId): bool;

  /**
   * Method assign.
   *
   * Executes the assign operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param ?string $interventionId the intervention id value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the assign result
   */
  public function assign(
    InterventionResourceType $type,
    string $resourceId,
    ?string $interventionId,
    ?string $clientId,
  ): InterventionResourceAssignment;

  /**
   * Method touchDraftIntervention.
   *
   * Executes the touch draft intervention operation.
   *
   * @since 1.0.0
   *
   * @param ?string $interventionId the intervention id value
   */
  public function touchDraftIntervention(?string $interventionId): void;

  /**
   * Method summary.
   *
   * Executes the summary operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return InterventionResourceSummary the summary result
   */
  public function summary(string $interventionId): InterventionResourceSummary;

  /**
   * Method workItemSummary.
   *
   * Executes the work item summary operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return InterventionWorkItemSummary the work item summary result
   */
  public function workItemSummary(string $interventionId): InterventionWorkItemSummary;

  /**
   * Method listMetrics.
   *
   * Aggregates the metrics required to render a intervention collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   *
   * @return array<string, InterventionListMetrics> metrics indexed by intervention id
   */
  public function listMetrics(array $interventionIds): array;

  /**
   * Method equipmentDrafts.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return list<InterventionEquipmentDraft>
   */
  public function equipmentDrafts(string $interventionId): array;
}
