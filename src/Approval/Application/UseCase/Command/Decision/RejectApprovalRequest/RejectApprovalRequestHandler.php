<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Command\Decision\RejectApprovalRequest;

use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort, ApprovalRequestRepositoryPort};
use Approval\Domain\Event\Request\ApprovalRejectedEvent;
use Approval\Domain\Exception\{
  ApprovalAccessDeniedException,
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  SelfApprovalNotAllowedException
};
use Approval\Domain\ValueObject\ApprovalRequestId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * UseCase RejectApprovalRequestHandler.
 *
 * Self-enforces `organization.approvals.decide`. The deferred action is
 * never executed on rejection.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RejectApprovalRequestHandler implements CommandHandler
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
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private ApprovalRequestRepositoryPort $requests,
    private ApprovalPolicyPort $policy,
    private ApprovalMemberDirectoryPort $memberDirectory,
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
   * @param RejectApprovalRequestCommand $command the command payload
   *
   * @return RejectApprovalRequestResult the use case result
   */
  public function __invoke(RejectApprovalRequestCommand $command): RejectApprovalRequestResult
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

    $request->reject($approverMemberId, $command->actorUserId, $command->decisionNote, $now);
    $this->requests->save($request);

    $this->eventDispatcher->dispatch(new ApprovalRejectedEvent(
      organizationId: $request->organizationId(),
      requestId: (string) $request->id(),
      actionType: $request->actionType(),
      subjectId: $request->subjectId(),
      decisionByMemberId: $approverMemberId,
      decisionByUserId: $command->actorUserId,
    ));

    return RejectApprovalRequestResult::fromDomain($request);
  }
  // #endregion
}
