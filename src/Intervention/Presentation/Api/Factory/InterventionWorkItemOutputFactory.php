<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Dto\Output\{InterventionAssigneeOutput, InterventionTargetOutput, InterventionWorkItemOutput};
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;
use LogicException;
use Organization\Application\UseCase\Query\Organization\GetOrganizationMember\{GetOrganizationMemberQuery, GetOrganizationMemberResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_key_exists;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Factory InterventionWorkItemOutputFactory.
 *
 * @category Factory
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionWorkItemOutputFactory
{
  use InterventionWorkflowViewDataTrait;

  /**
   * Property memberCache.
   *
   * Per-request cache of resolved members, keyed by member id, so a list of
   * work items sharing assignees resolves each member only once.
   *
   * @since 1.1.0
   *
   * @var array<string, GetOrganizationMemberResult|null>
   */
  private array $memberCache = [];

  /**
   * Property userCache.
   *
   * Per-request cache of resolved users, keyed by user id.
   *
   * @since 1.1.0
   *
   * @var array<string, GetUserResult|null>
   */
  private array $userCache = [];

  /**
   * Property targetCache.
   *
   * Per-request cache of resolved targets, keyed by target IRI.
   *
   * @since 1.1.0
   *
   * @var array<string, InterventionTargetOutput|null>
   */
  private array $targetCache = [];

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionWorkItemOutputFactory class.
   *
   * @since 1.1.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param ?\Intervention\Application\Service\InterventionWorkItemCapabilities $capabilities resolves caller-specific operational and time-journal actions
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly ?\Intervention\Application\Service\InterventionWorkItemCapabilities $capabilities = null,
  ) {
  }

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowView $view the view value
   * @param ?string $userId authenticated account identifier used for authorization
   *
   * @return InterventionWorkItemOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view, ?string $userId = null): InterventionWorkItemOutput
  {
    $data = $view->data;
    $output = new InterventionWorkItemOutput();
    $output->id = $this->string($data, 'id');
    $output->intervention = $this->string($data, 'intervention');
    $output->action = $this->string($data, 'action');
    $output->target = $this->nullableString($data, 'target');
    $output->targetSummary = $this->resolveTarget($output->target, $view->organizationId);
    $output->resultResource = $this->nullableString($data, 'resultResource');
    $output->assignee = $this->nullableString($data, 'assignee');
    $output->assigneeProfile = $this->resolveAssignee($output->assignee);
    $output->source = $this->string($data, 'source');
    $output->status = $this->string($data, 'status');
    $output->required = $this->boolean($data, 'required');
    $output->skipReason = $this->nullableString($data, 'skipReason');
    $output->estimatedMinutes = null === ($data['estimatedMinutes'] ?? null) ? null : $this->integer($data, 'estimatedMinutes');
    $output->remainingMinutes = null === ($data['remainingMinutes'] ?? null) ? null : $this->integer($data, 'remainingMinutes');
    $output->workStartsOn = $this->nullableString($data, 'workStartsOn');
    $output->workEndsOn = $this->nullableString($data, 'workEndsOn');
    $output->spentMinutes = $this->integer($data, 'spentMinutes');
    if (null !== $userId) {
      if (null === $this->capabilities) {
        throw new LogicException('Caller-specific capabilities require the work item policy.');
      }
      $output->allowedActions = $this->capabilities->forCaller($output->id, $output->status, $userId);
    }
    $output->evidenceCount = $this->integer($data, 'evidenceCount');
    $output->revision = $this->integer($data, 'revision');
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }

  /**
   * Method resolveAssignee.
   *
   * Resolves the assignee member reference into a display identity (name and
   * avatar). Returns null when the work item is unassigned or the member can
   * no longer be resolved, so a missing user never breaks work item listing.
   *
   * @since 1.1.0
   *
   * @param ?string $assignee the assignee member IRI
   *
   * @return ?InterventionAssigneeOutput the resolved assignee identity
   */
  private function resolveAssignee(?string $assignee): ?InterventionAssigneeOutput
  {
    $memberId = self::assigneeMemberId($assignee);
    if (null === $assignee || null === $memberId) {
      return null;
    }

    $member = $this->findMember($memberId);
    if (!$member instanceof GetOrganizationMemberResult) {
      return null;
    }

    $output = new InterventionAssigneeOutput();
    $output->member = $assignee;
    $output->userId = $member->userId;
    $output->displayName = $member->userId;

    $userResult = $this->findUser($member->userId);
    if ($userResult instanceof GetUserResult && null !== $userResult->user) {
      $output->firstName = $userResult->user->firstName;
      $output->lastName = $userResult->user->lastName;
      $output->displayName = trim($userResult->user->firstName . ' ' . $userResult->user->lastName)
        ?: ($userResult->user->username ?: $member->userId);
      $output->avatarUrl = $userResult->user->avatarUrl;
    }

    return $output;
  }

  /**
   * Method assigneeMemberId.
   *
   * @since 1.1.0
   *
   * @param ?string $assignee the member IRI, if assigned
   *
   * @return ?string the member ID for a valid IRI
   */
  private static function assigneeMemberId(?string $assignee): ?string
  {
    if (null === $assignee || '' === $assignee) {
      return null;
    }

    try {
      return ResourceIriParser::memberId($assignee);
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method resolveTarget.
   *
   * Resolves the polymorphic target reference (facility or equipment) into a
   * display summary. Returns null when there is no target, the target is free
   * text (non-IRI), or the linked resource can no longer be resolved, so a
   * missing target never breaks work item listing.
   *
   * @since 1.1.0
   *
   * @param ?string $target the target IRI
   * @param string $organizationId the owning organization id
   *
   * @return ?InterventionTargetOutput the resolved target summary
   */
  private function resolveTarget(?string $target, string $organizationId): ?InterventionTargetOutput
  {
    if (null === $target || '' === $target) {
      return null;
    }

    if (array_key_exists($target, $this->targetCache)) {
      return $this->targetCache[$target];
    }

    return $this->targetCache[$target] = $this->buildTargetSummary($target, $organizationId);
  }

  /**
   * Method buildTargetSummary.
   *
   * Detects the target kind from its IRI and resolves the matching resource
   * through the query bus, mirroring the labels used by the planning options.
   *
   * @since 1.1.0
   *
   * @param string $target the target IRI
   * @param string $organizationId the owning organization id
   *
   * @return ?InterventionTargetOutput the resolved target summary
   */
  private function buildTargetSummary(string $target, string $organizationId): ?InterventionTargetOutput
  {
    try {
      return match (true) {
        str_starts_with($target, '/api/facilities/') => $this->facilitySummary($target, $organizationId),
        str_starts_with($target, '/api/equipment/') => $this->equipmentSummary($target, $organizationId),
        default => null,
      };
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method facilitySummary.
   *
   * @since 1.1.0
   *
   * @param string $target the facility IRI
   * @param string $organizationId the owning organization id
   *
   * @return InterventionTargetOutput the facility target summary
   */
  private function facilitySummary(string $target, string $organizationId): InterventionTargetOutput
  {
    /** @var GetFacilityResult $result */
    $result = $this->queryBus->ask(new GetFacilityQuery(
      organizationId: $organizationId,
      facilityId: ResourceIriParser::id($target, 'facilities'),
    ));

    $output = new InterventionTargetOutput();
    $output->resource = $target;
    $output->kind = 'facility';
    $output->label = $result->name;

    return $output;
  }

  /**
   * Method equipmentSummary.
   *
   * @since 1.1.0
   *
   * @param string $target the equipment IRI
   * @param string $organizationId the owning organization id
   *
   * @return InterventionTargetOutput the equipment target summary
   */
  private function equipmentSummary(string $target, string $organizationId): InterventionTargetOutput
  {
    $equipmentId = ResourceIriParser::id($target, 'equipment');

    /** @var GetEquipmentResult $result */
    $result = $this->queryBus->ask(new GetEquipmentQuery(
      organizationId: $organizationId,
      equipmentId: $equipmentId,
    ));

    $output = new InterventionTargetOutput();
    $output->resource = $target;
    $output->kind = 'equipment';
    $output->label = sprintf('%s · %s', $result->type, $result->serialNumber ?: $equipmentId);

    return $output;
  }

  /**
   * Method findMember.
   *
   * Resolves an organization member by id through the query bus, caching the
   * result per request. A resolution failure yields null instead of bubbling.
   *
   * @since 1.1.0
   *
   * @param string $memberId the organization member id value
   *
   * @return ?GetOrganizationMemberResult the resolved member
   */
  private function findMember(string $memberId): ?GetOrganizationMemberResult
  {
    if (array_key_exists($memberId, $this->memberCache)) {
      return $this->memberCache[$memberId];
    }

    try {
      /** @var GetOrganizationMemberResult $result */
      $result = $this->queryBus->ask(new GetOrganizationMemberQuery($memberId));
    } catch (Throwable) {
      $result = null;
    }

    return $this->memberCache[$memberId] = $result;
  }

  /**
   * Method findUser.
   *
   * Resolves a user by id through the query bus, caching the result per request.
   *
   * @since 1.1.0
   *
   * @param string $userId the user id value
   *
   * @return ?GetUserResult the resolved user
   */
  private function findUser(string $userId): ?GetUserResult
  {
    if (array_key_exists($userId, $this->userCache)) {
      return $this->userCache[$userId];
    }

    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery($userId));
    } catch (Throwable) {
      $result = null;
    }

    return $this->userCache[$userId] = $result;
  }
}
