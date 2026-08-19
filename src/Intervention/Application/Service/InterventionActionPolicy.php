<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use Intervention\Application\Contract\Workflow\InterventionWorkflowContext;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Intervention\Domain\ValueObject\InterventionStatus;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

use function array_key_exists;
use function array_unique;
use function array_values;
use function in_array;
use function is_string;

/**
 * Service InterventionActionPolicy.
 *
 * The single source of truth for "what may this caller do to this
 * intervention right now" — used from both sides of the workflow:
 * {@see \Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\MutateInterventionWorkflowHandler}
 * calls {@see self::requiredPermissions()} to ENFORCE a mutation, and
 * {@see \Intervention\Presentation\Api\Factory\InterventionOutputFactory}
 * calls {@see self::allowedActions()} to ADVERTISE the caller's capabilities
 * on `InterventionOutput.allowedActions`, so a frontend menu never drifts
 * from what the backend actually allows.
 *
 * `requiredPermission()`/`requiredPermissions()` are the permission matrix
 * moved verbatim out of `MutateInterventionWorkflowHandler` (previously
 * private methods there) — this is the ONE piece of logic both call sites
 * share literally, including the AND-rule: a replan of a non-draft
 * intervention needs `organization.interventions.plan` ON TOP OF the base
 * permission when the payload also touches non-planning fields, and ALONE
 * when it is planning-only.
 *
 * `allowedActions()` composes that same matrix (via synthetic single-field
 * payloads) with the field-mutability windows from
 * {@see InterventionMutabilityPolicy}, the transition legality from
 * {@see InterventionTransitionPolicy}, the change-creation window from
 * {@see InterventionChangePolicy}, and the responsible/participant identity
 * from {@see InterventionMemberPolicy} — the same four collaborators
 * `MutateInterventionWorkflowHandler` and the workflow gateway adapter
 * consult on write. Two flags cannot reuse the matrix literally because
 * their mutation never goes through `MutateInterventionWorkflowHandler`:
 * `canManageAttachments` mirrors {@see InterventionResourceManager::mutationPermission()}
 * (kept as a documented parallel rather than a literal call, since that
 * method re-reads the intervention from storage — unsuitable for a
 * per-row list computation) and `canDelete`'s draft/abandoned window
 * mirrors the inline check in the Infrastructure gateway adapter's
 * `mutateIntervention()`, which has no Domain-level equivalent to share.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionActionPolicy
{
  private const string PERMISSION_PLAN = 'organization.interventions.plan';

  private const string PERMISSION_EXECUTE = 'organization.interventions.execute';

  private const string PERMISSION_REVIEW = 'organization.interventions.review';

  private const string PERMISSION_PUBLISH = 'organization.interventions.publish';

  /**
   * Statuses from which an intervention may be deleted — mirrors the inline
   * check in `DoctrineInterventionWorkflowGatewayAdapter::mutateIntervention()`.
   */
  private const array DELETABLE_STATUSES = [InterventionStatus::DRAFT, InterventionStatus::ABANDONED];

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param InterventionMemberPolicy $memberPolicy the intervention member policy
   * @param InterventionTransitionPolicy $transitionPolicy the status-transition policy
   * @param InterventionMutabilityPolicy $mutabilityPolicy the field-mutability window policy
   * @param InterventionChangePolicy $changePolicy the proposed-change policy
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private InterventionMemberPolicy $memberPolicy,
    private InterventionTransitionPolicy $transitionPolicy,
    private InterventionMutabilityPolicy $mutabilityPolicy,
    private InterventionChangePolicy $changePolicy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method requiredPermissions.
   *
   * Every permission a mutation requires — ALL of them (AND). A payload
   * touching planning fields on a non-draft intervention is a replan and
   * requires `plan`: alone when the payload is planning-only (a planner who
   * is neither responsible nor participant may reschedule), on top of the
   * base permission when the payload also transitions or edits other
   * fields. Moved out of `MutateInterventionWorkflowHandler` unchanged so
   * the handler and {@see self::allowedActions()} consult the same matrix.
   *
   * @since 1.0.0
   *
   * @param string $resource the mutated resource ('intervention', 'work_item', 'change', …)
   * @param string $action the mutation action ('create', 'update', 'delete')
   * @param array<string, mixed> $payload the mutation payload
   * @param string $contextStatus the intervention's current status
   *
   * @return non-empty-list<string> the required permissions
   */
  public function requiredPermissions(string $resource, string $action, array $payload, string $contextStatus): array
  {
    $base = $this->requiredPermission($resource, $action, $payload, $contextStatus);
    if ('intervention' !== $resource || 'create' === $action || 'draft' === $contextStatus) {
      return [$base];
    }

    $touchesPlanning = false;
    foreach (['siteId', 'responsibleId', 'participants', 'priority', 'plannedStartAt', 'dueAt'] as $field) {
      if (array_key_exists($field, $payload)) {
        $touchesPlanning = true;

        break;
      }
    }
    if (!$touchesPlanning) {
      return [$base];
    }

    $editsBeyondPlanning = false;
    foreach (['status', 'name', 'description', 'reviewNote', 'labelIds'] as $field) {
      if (array_key_exists($field, $payload)) {
        $editsBeyondPlanning = true;

        break;
      }
    }

    return $editsBeyondPlanning
      ? array_values(array_unique([$base, self::PERMISSION_PLAN]))
      : [self::PERMISSION_PLAN];
  }

  /**
   * Method allowedActions.
   *
   * The caller-specific action-capability surface for one intervention,
   * computed entirely from data the caller already loaded — the
   * organization's granted permissions (memoized per request by
   * {@see OrganizationAuthorizationPort}) and the caller's own organization
   * member id (resolved once, not per row). No additional query per
   * intervention: safe to call for every row of a list.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowContext $context the intervention's workflow context
   * @param string $userId the caller's user id
   *
   * @return InterventionAllowedActions the allowed-action surface
   */
  public function allowedActions(InterventionWorkflowContext $context, string $userId): InterventionAllowedActions
  {
    $status = InterventionStatus::tryFrom($context->status);
    if (!$status instanceof InterventionStatus) {
      return new InterventionAllowedActions(
        canEditDetails: false,
        canEditSite: false,
        canEditResponsible: false,
        canEditPlanning: false,
        canMutateWorkItems: false,
        canMutateChanges: false,
        canAssignTeam: false,
        canManageAttachments: false,
        canSubmit: false,
        canWithdraw: false,
        canDelete: false,
        canPublish: false,
      );
    }

    $callerMemberId = $this->memberPolicy->findMemberId($context->organizationId, $userId);
    $isResponsible = null !== $callerMemberId && null !== $context->responsibleId && $callerMemberId === $context->responsibleId;
    $isParticipant = null !== $callerMemberId && in_array($callerMemberId, $context->participants, true);
    $isDraft = InterventionStatus::DRAFT === $status;

    $hasExecute = $this->hasPermission($context->organizationId, $userId, self::PERMISSION_EXECUTE);
    $hasPublish = $this->hasPermission($context->organizationId, $userId, self::PERMISSION_PUBLISH);

    return new InterventionAllowedActions(
      canEditDetails: $this->mutabilityPolicy->isMutable($status)
        && $this->hasAllPermissions($context->organizationId, $userId, 'intervention', 'update', ['name' => ''], $status),
      canEditSite: $this->mutabilityPolicy->isScopeMutable($status)
        && $this->hasAllPermissions($context->organizationId, $userId, 'intervention', 'update', ['siteId' => null], $status),
      canEditResponsible: $this->mutabilityPolicy->isOwnershipMutable($status)
        && $this->hasAllPermissions($context->organizationId, $userId, 'intervention', 'update', ['responsibleId' => null], $status),
      canEditPlanning: $this->mutabilityPolicy->isScheduleMutable($status)
        && $this->hasAllPermissions($context->organizationId, $userId, 'intervention', 'update', ['participants' => []], $status),
      canMutateWorkItems: $this->mutabilityPolicy->isScheduleMutable($status)
        && $this->hasAllPermissions($context->organizationId, $userId, 'work_item', 'create', [], $status)
        && ($isDraft || $isResponsible || $isParticipant),
      canMutateChanges: $hasExecute
        && $this->isChangeCreationWindow($status)
        && ($isResponsible || $isParticipant),
      canAssignTeam: $this->mutabilityPolicy->isScheduleMutable($status)
        && $this->hasAllPermissions($context->organizationId, $userId, 'intervention', 'update', ['participants' => []], $status),
      canManageAttachments: $this->mutabilityPolicy->isScheduleMutable($status)
        && ($isDraft
          ? $this->hasPermission($context->organizationId, $userId, self::PERMISSION_PLAN)
          : ($hasExecute && ($isResponsible || $isParticipant))),
      canSubmit: $hasExecute
        && $isResponsible
        && in_array(InterventionStatus::SUBMITTED, $this->transitionPolicy->allowedFrom($status), true),
      canWithdraw: $hasExecute
        && $isResponsible
        && InterventionStatus::SUBMITTED === $status
        && in_array(InterventionStatus::IN_PROGRESS, $this->transitionPolicy->allowedFrom($status), true),
      canDelete: in_array($status, self::DELETABLE_STATUSES, true)
        && $this->hasAllPermissions($context->organizationId, $userId, 'intervention', 'delete', [], $status),
      canPublish: $hasPublish && InterventionStatus::SUBMITTED === $status,
    );
  }

  /**
   * Method requiredPermission.
   *
   * Moved out of `MutateInterventionWorkflowHandler` unchanged.
   *
   * @since 1.0.0
   *
   * @param string $resource the mutated resource value
   * @param string $action the mutation action value
   * @param array<string, mixed> $payload the mutation payload value
   * @param string $contextStatus the intervention's current status
   *
   * @return string the permission result
   */
  private function requiredPermission(string $resource, string $action, array $payload, string $contextStatus): string
  {
    if ('intervention' === $resource) {
      if ('create' === $action) {
        return self::PERMISSION_PLAN;
      }
      $targetStatus = $payload['status'] ?? null;
      if (is_string($targetStatus)) {
        return match ($targetStatus) {
          'planned' => self::PERMISSION_PLAN,
          'in_progress', 'submitted' => self::PERMISSION_EXECUTE,
          'abandoned' => match ($contextStatus) {
            'draft' => self::PERMISSION_PLAN,
            'changes_requested' => self::PERMISSION_REVIEW,
            default => self::PERMISSION_EXECUTE,
          },
          'changes_requested' => self::PERMISSION_REVIEW,
          default => self::PERMISSION_PLAN,
        };
      }

      return 'draft' === $contextStatus ? self::PERMISSION_PLAN : self::PERMISSION_EXECUTE;
    }

    if ('work_item' === $resource) {
      return 'draft' === $contextStatus ? self::PERMISSION_PLAN : self::PERMISSION_EXECUTE;
    }

    return 'submitted' === $contextStatus && 'create' !== $action
      ? self::PERMISSION_REVIEW
      : self::PERMISSION_EXECUTE;
  }

  /**
   * Method hasAllPermissions.
   *
   * Resolves {@see self::requiredPermissions()} for a synthetic single-field
   * mutation and checks every returned permission is granted — the same
   * matrix `MutateInterventionWorkflowHandler` enforces for an actual
   * mutation of that field.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the caller's user id
   * @param string $resource the mutated resource ('intervention', 'work_item')
   * @param string $action the mutation action ('create', 'update', 'delete')
   * @param array<string, mixed> $payload the synthetic single-field payload
   * @param InterventionStatus $status the intervention's current status
   *
   * @return bool true when every required permission is granted
   */
  private function hasAllPermissions(
    string $organizationId,
    string $userId,
    string $resource,
    string $action,
    array $payload,
    InterventionStatus $status,
  ): bool {
    foreach ($this->requiredPermissions($resource, $action, $payload, $status->value) as $permission) {
      if (!$this->hasPermission($organizationId, $userId, $permission)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Method hasPermission.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $userId the user id value
   * @param string $permission the permission name
   *
   * @return bool true when the permission is granted
   */
  private function hasPermission(string $organizationId, string $userId, string $permission): bool
  {
    return $this->authorization->hasPermission($userId, $organizationId, $permission);
  }

  /**
   * Method isChangeCreationWindow.
   *
   * Whether a proposed change may be created in the given status — mirrors
   * {@see InterventionChangePolicy::assertCanCreate()}, the single source
   * both this method and `DoctrineInterventionWorkflowGatewayAdapter::createChange()`
   * consult, turned into a boolean rather than a thrown exception.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $status the intervention's current status
   *
   * @return bool true when a change may be created
   */
  private function isChangeCreationWindow(InterventionStatus $status): bool
  {
    try {
      $this->changePolicy->assertCanCreate($status);

      return true;
    } catch (InterventionConflictException) {
      return false;
    }
  }
  // #endregion
}
