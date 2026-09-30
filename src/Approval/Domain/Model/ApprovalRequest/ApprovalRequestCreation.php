<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use Approval\Domain\ValueObject\ApprovalRequestId;

/** Immutable data shared by a new approval and its atomic pending reservation. */
final readonly class ApprovalRequestCreation
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the immutable values submitted when an approval request is created.
   *
   * @access public
   *
   * @param ApprovalRequestId $id identifier assigned to the approval request
   * @param string $organizationId organization that owns the protected action
   * @param string $actionType registered action type being gated
   * @param string $subjectId identifier of the resource targeted by the action
   * @param ApprovalRequestSubmission $submission validated action payload retained for later execution
   * @param ApprovalRequestSchedule $schedule creation and expiry timestamps for the request
   *
   * @return void
   */
  public function __construct(
    public ApprovalRequestId $id,
    public string $organizationId,
    public string $actionType,
    public string $subjectId,
    public ApprovalRequestSubmission $submission,
    public ApprovalRequestSchedule $schedule,
  ) {
  }
  // #endregion
}
