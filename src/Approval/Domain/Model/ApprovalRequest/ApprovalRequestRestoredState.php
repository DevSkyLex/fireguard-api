<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use Approval\Domain\ValueObject\ApprovalStatus;
use DateTimeImmutable;

/** Persisted approval state supplied by the mapper without changing stored fields. */
final readonly class ApprovalRequestRestoredState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups the creation, status, resolution, and update timestamp used to restore an approval request.
   *
   * @access public
   *
   * @param ApprovalRequestCreation $creation immutable values captured at request creation
   * @param ApprovalStatus $status current approval request status
   * @param DateTimeImmutable $updatedAt time the request state was last updated
   * @param ApprovalRequestResolution $resolution decision and execution details, if present
   *
   * @return void
   */
  public function __construct(
    public ApprovalRequestCreation $creation,
    public ApprovalStatus $status,
    public DateTimeImmutable $updatedAt,
    public ApprovalRequestResolution $resolution,
  ) {
  }
  // #endregion
}
