<?php

declare(strict_types=1);

namespace Approval\Application\UseCase\Query\Request\GetApprovalRequest;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetApprovalRequestQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetApprovalRequestQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $requestId the approval request identifier
   * @param string $userId the requesting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $userId,
  ) {
  }
  // #endregion
}
