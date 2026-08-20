<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Query\Request\GetApprovalRequest;

use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Domain\Exception\{ApprovalAccessDeniedException, ApprovalRequestNotFoundException};
use Approval\Domain\ValueObject\ApprovalRequestId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetApprovalRequestHandler.
 *
 * Self-enforces `organization.approvals.read`, separating "not a member of
 * this organization" (404, the same answer an unknown request identifier
 * produces) from "member, but not entitled" (403) — see
 * {@see \Organization\Application\Contract\Authorization\OrganizationAccessDecision}.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetApprovalRequestHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestRepositoryPort $requests the approval request repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private ApprovalRequestRepositoryPort $requests,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetApprovalRequestQuery $query the query payload
   *
   * @return GetApprovalRequestResult the query result
   */
  public function __invoke(GetApprovalRequestQuery $query): GetApprovalRequestResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.approvals.read');
    if ($decision->isOutsideScope()) {
      throw ApprovalRequestNotFoundException::withId($query->requestId);
    }
    if (!$decision->isGranted()) {
      throw ApprovalAccessDeniedException::missingPermission('organization.approvals.read');
    }

    $request = $this->requests->findById(ApprovalRequestId::fromString($query->requestId));

    if (null === $request || $request->organizationId() !== $query->organizationId) {
      throw ApprovalRequestNotFoundException::withId($query->requestId);
    }

    return GetApprovalRequestResult::fromDomain($request);
  }
  // #endregion
}
