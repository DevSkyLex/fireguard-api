<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class ApprovalHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner mutations, transaction ownership and bounded replay result schemas.
 *
 * @category Adapter
 */
final readonly class ApprovalHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> registered owner mutations requiring client keys
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'approveApprovalRequest',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\ApproveApprovalRequestCommand::class,
        resultClass: \Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\ApproveApprovalRequestResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'approval_request',
      ),
      new HttpIdempotencyOperation(
        name: 'rejectApprovalRequest',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\RejectApprovalRequestCommand::class,
        resultClass: \Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\RejectApprovalRequestResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'approval_request',
      ),
      new HttpIdempotencyOperation(
        name: 'withdrawApprovalRequest',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\WithdrawApprovalRequestCommand::class,
        resultClass: \Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\WithdrawApprovalRequestResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'approval_request',
      ),
    ];
  }
  // #endregion
}
