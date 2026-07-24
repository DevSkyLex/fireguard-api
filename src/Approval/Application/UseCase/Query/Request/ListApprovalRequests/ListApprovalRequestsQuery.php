<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Query\Request\ListApprovalRequests;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListApprovalRequestsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListApprovalRequestsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $userId the requesting user identifier
   * @param ?string $status optional status filter
   * @param ?string $actionType optional action type filter
   * @param int $page the requested page number (1-based)
   * @param int $itemsPerPage the requested page size
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public ?string $status = null,
    public ?string $actionType = null,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
  // #endregion
}
