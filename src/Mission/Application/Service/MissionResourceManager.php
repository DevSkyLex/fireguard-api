<?php

declare(strict_types=1);

namespace Mission\Application\Service;

use Mission\Application\Contract\Resource\{MissionAssignmentContext, MissionResourceAssignment};
use Mission\Application\Port\Outbound\MissionResourceGatewayPort;
use Mission\Domain\Exception\{
  ClientResourceAlreadyExistsException,
  MissionConflictException,
  MissionNotFoundException,
  MissionResourceNotFoundException
};
use Mission\Domain\ValueObject\MissionResourceType;

use function in_array;

/**
 * Service MissionResourceManager.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionResourceManager
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionResourceManager class.
   *
   * @since 1.0.0
   *
   * @param MissionResourceGatewayPort $resources the resources value
   * @param ?MissionMemberPolicy $memberPolicy the mission member policy value
   */
  public function __construct(
    private MissionResourceGatewayPort $resources,
    private ?MissionMemberPolicy $memberPolicy = null,
  ) {
  }

  /**
   * Method attach.
   *
   * Executes the attach operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   * @param ?string $missionId the mission id value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the attach result
   */
  public function attach(
    MissionResourceType $type,
    string $resourceId,
    string $organizationId,
    ?string $missionId,
    ?string $clientId = null,
  ): MissionResourceAssignment {
    if (!$this->resources->resourceExists($type, $resourceId)) {
      throw MissionResourceNotFoundException::withId($type, $resourceId);
    }

    if (null === $missionId || '' === $missionId) {
      return $this->resources->assign($type, $resourceId, null, $clientId);
    }

    $mission = $this->resources->missionAssignmentContext($missionId);
    if (null === $mission) {
      throw MissionNotFoundException::withId($missionId);
    }
    if ($mission->organizationId !== $organizationId) {
      throw new MissionConflictException('Mission and resource must belong to the same organization.');
    }
    if (!in_array($mission->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)) {
      throw new MissionConflictException('Resources can only be attached before mission submission.');
    }

    return $this->resources->assign($type, $resourceId, $missionId, $clientId);
  }

  /**
   * Method assertOfflineCreate.
   *
   * Executes the assert offline create operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param ?string $clientId the client id value
   */
  public function assertOfflineCreate(MissionResourceType $type, ?string $clientId): void
  {
    if (null === $clientId || '' === $clientId) {
      return;
    }
    if ($this->resources->clientIdExists($type, $clientId)) {
      throw new ClientResourceAlreadyExistsException('A resource with this client identifier already exists.');
    }
  }

  /**
   * Method missionContext.
   *
   * Executes the mission context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionAssignmentContext the mission context result
   */
  public function missionContext(string $missionId): ?MissionAssignmentContext
  {
    return $this->resources->missionAssignmentContext($missionId);
  }

  /**
   * Method resourceInMissionScope.
   *
   * Determines whether a canonical resource is targeted by a mission.
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
  ): bool {
    return $this->resources->resourceInMissionScope($type, $resourceId, $missionId);
  }

  /**
   * Method mutationPermission.
   *
   * Resolves the mission permission required to mutate a mission-owned
   * resource and rejects immutable mission states.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param ?string $userId the current user id value
   *
   * @return string the required permission name
   */
  public function mutationPermission(string $missionId, ?string $userId = null): string
  {
    $context = $this->resources->missionMutationContext($missionId);
    if (null === $context) {
      throw MissionNotFoundException::withId($missionId);
    }
    if (in_array($context->status, ['submitted', 'published', 'abandoned'], true)) {
      throw new MissionConflictException('Mission resources are immutable in the current state.');
    }
    if ('draft' !== $context->status && null !== $userId && null !== $this->memberPolicy) {
      $this->memberPolicy->assertCanExecuteMission(
        $context->organizationId,
        $userId,
        $context->responsibleId,
        $context->participants,
      );
    }

    return 'draft' === $context->status
      ? 'organization.missions.plan'
      : 'organization.missions.execute';
  }

  /**
   * Method touchDraftMission.
   *
   * Executes the touch draft mission operation.
   *
   * @since 1.0.0
   *
   * @param ?string $missionId the mission id value
   */
  public function touchDraftMission(?string $missionId): void
  {
    $this->resources->touchDraftMission($missionId);
  }
}
