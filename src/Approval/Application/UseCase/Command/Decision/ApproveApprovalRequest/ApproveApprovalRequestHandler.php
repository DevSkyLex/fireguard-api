<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort, ApprovalRequestRepositoryPort};
use Approval\Application\Service\ApprovalActionExecutorRegistry;
use Approval\Domain\Event\Request\{ApprovalApprovedEvent, ApprovalExecutionFailedEvent};
use Approval\Domain\Exception\{
  ApprovalAccessDeniedException,
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  DeferredActionNoLongerApplicableException,
  SelfApprovalNotAllowedException
};
use Approval\Domain\ValueObject\ApprovalRequestId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * UseCase ApproveApprovalRequestHandler.
 *
 * Self-enforces `organization.approvals.decide`. Executes the deferred
 * action SYNCHRONOUSLY, while the request is still `pending`, so the
 * approver gets immediate re-validation feedback: only once the executor
 * confirms the action applied (or was already in its target state — a
 * routine idempotent outcome) does the request transition to `approved`.
 * When the subject changed state since the request was created, the
 * executor signals it via {@see DeferredActionNoLongerApplicableException}
 * and the request is transitioned to `cancelled` instead, never `approved`
 * — an approved-but-unapplied request must never exist.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApproveApprovalRequestHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestRepositoryPort $requests the approval request repository port
   * @param ApprovalPolicyPort $policy the cross-module approval policy port
   * @param ApprovalMemberDirectoryPort $memberDirectory the cross-module member directory port
   * @param ApprovalActionExecutorRegistry $executors the deferred action executor registry
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private ApprovalRequestRepositoryPort $requests,
    private ApprovalPolicyPort $policy,
    private ApprovalMemberDirectoryPort $memberDirectory,
    private ApprovalActionExecutorRegistry $executors,
    private OrganizationAuthorizationPort $authorization,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ApproveApprovalRequestCommand $command the command payload
   *
   * @return ApproveApprovalRequestResult the use case result
   */
  public function __invoke(ApproveApprovalRequestCommand $command): ApproveApprovalRequestResult
  {
    $request = $this->requests->findById(ApprovalRequestId::fromString($command->requestId));

    if (null === $request || $request->organizationId() !== $command->organizationId) {
      throw ApprovalRequestNotFoundException::withId($command->requestId);
    }

    $decision = $this->authorization->resolveAccess($command->actorUserId, $command->organizationId, 'organization.approvals.decide');
    if ($decision->isOutsideScope()) {
      throw ApprovalRequestNotFoundException::withId($command->requestId);
    }
    if (!$decision->isGranted()) {
      throw ApprovalAccessDeniedException::missingPermission('organization.approvals.decide');
    }

    $approverMemberId = $this->memberDirectory->resolveMemberId($command->organizationId, $command->actorUserId);
    if (null === $approverMemberId) {
      throw ApprovalAccessDeniedException::missingPermission('organization.approvals.decide');
    }

    $policy = $this->policy->policyFor($command->organizationId);
    $minRole = $policy->minApproverRoleFor($request->actionType());

    if (!$this->memberDirectory->memberSatisfiesRole($command->organizationId, $approverMemberId, $minRole)) {
      throw ApproverNotAuthorizedException::belowMinimumRole($minRole);
    }

    if ($approverMemberId === $request->requestedByMemberId() && !$policy->allowSelfApproval) {
      throw SelfApprovalNotAllowedException::create();
    }

    if (!$request->isPending()) {
      throw ApprovalRequestNotPendingException::withId($command->requestId);
    }

    $now = $this->clock->now();

    try {
      $this->executors->execute(new DeferredActionContext(
        organizationId: $request->organizationId(),
        actionType: $request->actionType(),
        subjectId: $request->subjectId(),
        payload: $request->payload(),
      ));
    } catch (DeferredActionNoLongerApplicableException $exception) {
      $request->cancel($exception->getMessage(), $now);
      $this->requests->save($request);

      $this->eventDispatcher->dispatch(new ApprovalExecutionFailedEvent(
        organizationId: $request->organizationId(),
        requestId: (string) $request->id(),
        actionType: $request->actionType(),
        subjectId: $request->subjectId(),
        error: $exception->getMessage(),
        decisionByUserId: $command->actorUserId,
      ));

      throw $exception;
    }

    $request->approve($approverMemberId, $command->actorUserId, $command->decisionNote, $now);
    $request->markExecuted($now);
    $this->requests->save($request);

    $this->eventDispatcher->dispatch(new ApprovalApprovedEvent(
      organizationId: $request->organizationId(),
      requestId: (string) $request->id(),
      actionType: $request->actionType(),
      subjectId: $request->subjectId(),
      decisionByMemberId: $approverMemberId,
      decisionByUserId: $command->actorUserId,
    ));

    return ApproveApprovalRequestResult::fromDomain($request);
  }
  // #endregion
}
