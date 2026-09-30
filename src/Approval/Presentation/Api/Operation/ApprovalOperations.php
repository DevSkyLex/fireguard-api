<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Operation;

/**
 * Operation ApprovalOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalOperations
{
  /**
   * Constant LIST_APPROVAL_REQUESTS
   */
  public const string LIST_APPROVAL_REQUESTS = 'listApprovalRequests';

  /**
   * Constant GET_APPROVAL_REQUEST
   */
  public const string GET_APPROVAL_REQUEST = 'getApprovalRequest';

  /**
   * Constant APPROVE_APPROVAL_REQUEST
   */
  public const string APPROVE_APPROVAL_REQUEST = 'approveApprovalRequest';

  /**
   * Constant REJECT_APPROVAL_REQUEST
   */
  public const string REJECT_APPROVAL_REQUEST = 'rejectApprovalRequest';

  /**
   * Constant WITHDRAW_APPROVAL_REQUEST
   */
  public const string WITHDRAW_APPROVAL_REQUEST = 'withdrawApprovalRequest';

  /**
   * Constant LIST_APPROVAL_ACTION_TYPES
   */
  public const string LIST_APPROVAL_ACTION_TYPES = 'listApprovalActionTypes';
}
