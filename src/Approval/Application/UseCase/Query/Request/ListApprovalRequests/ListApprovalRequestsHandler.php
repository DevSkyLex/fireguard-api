<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Query\Request\ListApprovalRequests;

use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestResult;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function max;

/**
 * UseCase ListApprovalRequestsHandler.
 *
 * Self-enforces `organization.approvals.read`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListApprovalRequestsHandler implements QueryHandler
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
   * @param ListApprovalRequestsQuery $query the query payload
   *
   * @return ListApprovalRequestsResult the query result
   */
  public function __invoke(ListApprovalRequestsQuery $query): ListApprovalRequestsResult
  {
    $this->authorization->assertGrantedPermissions($query->userId, $query->organizationId, [
      'organization.approvals.read',
    ]);

    $itemsPerPage = max(1, $query->itemsPerPage);
    $offset = max(0, $query->page - 1) * $itemsPerPage;

    $requests = $this->requests->listByOrganization($query->organizationId, $query->status, $query->actionType, $itemsPerPage, $offset);
    $total = $this->requests->countByOrganization($query->organizationId, $query->status, $query->actionType);

    return new ListApprovalRequestsResult(
      items: array_map(GetApprovalRequestResult::fromDomain(...), $requests),
      page: $query->page,
      itemsPerPage: $itemsPerPage,
      total: $total,
    );
  }
  // #endregion
}
