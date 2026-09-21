<?php

declare(strict_types=1);

namespace Approval\Application\Service;

use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort};
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestResult;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Outbound\ClockPort;

/** Advisory capabilities for the current reader; commands recheck under the decision lock. */
final readonly class ApprovalRequestViewFactory
{
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private ApprovalPolicyPort $policies,
    private ApprovalMemberDirectoryPort $members,
    private ClockPort $clock,
  ) {
  }

  public function forUser(ApprovalRequest $request, string $userId): GetApprovalRequestResult
  {
    $reason = $this->blockedReason($request, $userId);

    $actions = null === $reason ? ['approve', 'reject'] : [];
    if ($request->isPending() && $request->expiresAt() > $this->clock->now()
      && $request->requestedByUserId() === $userId
      && $this->authorization->isMemberOf($userId, $request->organizationId())
      && null !== $this->members->resolveMemberId($request->organizationId(), $userId)) {
      $actions[] = 'withdraw';
    }

    return GetApprovalRequestResult::fromDomain($request, $actions, $reason);
  }

  private function blockedReason(ApprovalRequest $request, string $userId): ?string
  {
    if (!$request->isPending()) {
      return 'approval_not_pending';
    }
    if ($request->expiresAt() <= $this->clock->now()) {
      return 'approval_expired';
    }
    $org = $request->organizationId();
    if (!$this->authorization->hasPermission($userId, $org, 'organization.approvals.decide')) {
      return 'approval_permission_required';
    }
    $member = $this->members->resolveMemberId($org, $userId);
    if (null === $member) {
      return 'approval_permission_required';
    }
    $policy = $this->policies->policyFor($org);
    if (!$this->members->memberSatisfiesRole($org, $member, $policy->minApproverRoleFor($request->actionType()))) {
      return 'approval_role_required';
    }
    if ($member === $request->requestedByMemberId() && !$policy->allowSelfApproval) {
      return 'approval_self_decision_forbidden';
    }

    return null;
  }
}
