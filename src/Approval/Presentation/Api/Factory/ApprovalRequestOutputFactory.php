<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Factory;

use Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\ApproveApprovalRequestResult;
use Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\RejectApprovalRequestResult;
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestResult;
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;

/**
 * Factory ApprovalRequestOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param GetApprovalRequestResult|ApproveApprovalRequestResult|RejectApprovalRequestResult $view the query/command result view
   *
   * @return ApprovalRequestOutput the mapped output
   */
  public function fromView(GetApprovalRequestResult|ApproveApprovalRequestResult|RejectApprovalRequestResult $view): ApprovalRequestOutput
  {
    $output = new ApprovalRequestOutput();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->actionType = $view->actionType;
    $output->subjectId = $view->subjectId;
    $output->status = $view->status;
    $output->requestedByMemberId = $view->requestedByMemberId;
    $output->requestedByUserId = $view->requestedByUserId;
    $output->decisionByMemberId = $view->decisionByMemberId;
    $output->decisionByUserId = $view->decisionByUserId;
    $output->decisionNote = $view->decisionNote;
    $output->expiresAt = $view->expiresAt->format('c');
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');
    $output->decidedAt = $view->decidedAt?->format('c');
    $output->executedAt = $view->executedAt?->format('c');
    $output->executionError = $view->executionError;

    return $output;
  }
  // #endregion
}
