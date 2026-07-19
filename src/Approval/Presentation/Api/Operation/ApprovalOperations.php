<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Operation;

/**
 * Operation ApprovalOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalOperations
{
  public const string LIST_APPROVAL_REQUESTS = 'listApprovalRequests';

  public const string GET_APPROVAL_REQUEST = 'getApprovalRequest';

  public const string APPROVE_APPROVAL_REQUEST = 'approveApprovalRequest';

  public const string REJECT_APPROVAL_REQUEST = 'rejectApprovalRequest';

  public const string LIST_APPROVAL_ACTION_TYPES = 'listApprovalActionTypes';
}
