<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Query\Request\ListApprovalRequests;

use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListApprovalRequestsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListApprovalRequestsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetApprovalRequestResult> $items the page of approval requests
   * @param int $page the current page number
   * @param int $itemsPerPage the page size
   * @param int $total the total matching approval request count
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
  // #endregion
}
