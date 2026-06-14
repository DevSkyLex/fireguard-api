<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Resource\{InterventionAssignmentContext, InterventionResourceAssignment};
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Domain\Exception\{
  ClientResourceAlreadyExistsException,
  InterventionConflictException,
  InterventionNotFoundException,
  InterventionResourceNotFoundException
};
use Intervention\Domain\ValueObject\InterventionResourceType;

use function in_array;

/**
 * Service InterventionResourceManager.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionResourceManager
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionResourceManager class.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceGatewayPort $resources the resources value
   * @param ?InterventionMemberPolicy $memberPolicy the intervention member policy value
   */
  public function __construct(
    private InterventionResourceGatewayPort $resources,
    private ?InterventionMemberPolicy $memberPolicy = null,
  ) {
  }

  /**
   * Method attach.
   *
   * Executes the attach operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   * @param ?string $interventionId the intervention id value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the attach result
   */
  public function attach(
    InterventionResourceType $type,
    string $resourceId,
    string $organizationId,
    ?string $interventionId,
    ?string $clientId = null,
  ): InterventionResourceAssignment {
    if (!$this->resources->resourceExists($type, $resourceId)) {
      throw InterventionResourceNotFoundException::withId($type, $resourceId);
    }

    if (null === $interventionId || '' === $interventionId) {
      return $this->resources->assign($type, $resourceId, null, $clientId);
    }

    $intervention = $this->resources->interventionAssignmentContext($interventionId);
    if (null === $intervention) {
      throw InterventionNotFoundException::withId($interventionId);
    }
    if ($intervention->organizationId !== $organizationId) {
      throw new InterventionConflictException('Intervention and resource must belong to the same organization.');
    }
    if (!in_array($intervention->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)) {
      throw new InterventionConflictException('Resources can only be attached before intervention submission.');
    }

    return $this->resources->assign($type, $resourceId, $interventionId, $clientId);
  }

  /**
   * Method assertOfflineCreate.
   *
   * Executes the assert offline create operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param ?string $clientId the client id value
   */
  public function assertOfflineCreate(InterventionResourceType $type, ?string $clientId): void
  {
    if (null === $clientId || '' === $clientId) {
      return;
    }
    if ($this->resources->clientIdExists($type, $clientId)) {
      throw new ClientResourceAlreadyExistsException('A resource with this client identifier already exists.');
    }
  }

  /**
   * Method interventionContext.
   *
   * Executes the intervention context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionAssignmentContext the intervention context result
   */
  public function interventionContext(string $interventionId): ?InterventionAssignmentContext
  {
    return $this->resources->interventionAssignmentContext($interventionId);
  }

  /**
   * Method resourceInInterventionScope.
   *
   * Determines whether a canonical resource is targeted by a intervention.
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
  ): bool {
    return $this->resources->resourceInInterventionScope($type, $resourceId, $interventionId);
  }

  /**
   * Method mutationPermission.
   *
   * Resolves the intervention permission required to mutate a intervention-owned
   * resource and rejects immutable intervention states.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param ?string $userId the current user id value
   *
   * @return string the required permission name
   */
  public function mutationPermission(string $interventionId, ?string $userId = null): string
  {
    $context = $this->resources->interventionMutationContext($interventionId);
    if (null === $context) {
      throw InterventionNotFoundException::withId($interventionId);
    }
    if (in_array($context->status, ['submitted', 'published', 'abandoned'], true)) {
      throw new InterventionConflictException('Intervention resources are immutable in the current state.');
    }
    if ('draft' !== $context->status && null !== $userId && null !== $this->memberPolicy) {
      $this->memberPolicy->assertCanExecuteIntervention(
        $context->organizationId,
        $userId,
        $context->responsibleId,
        $context->participants,
      );
    }

    return 'draft' === $context->status
      ? 'organization.interventions.plan'
      : 'organization.interventions.execute';
  }

  /**
   * Method touchDraftIntervention.
   *
   * Executes the touch draft intervention operation.
   *
   * @since 1.0.0
   *
   * @param ?string $interventionId the intervention id value
   */
  public function touchDraftIntervention(?string $interventionId): void
  {
    $this->resources->touchDraftIntervention($interventionId);
  }
}
