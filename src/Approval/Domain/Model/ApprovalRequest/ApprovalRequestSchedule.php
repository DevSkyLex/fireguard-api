<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use DateTimeImmutable;

/** The submission time and expiry deadline of an approval request. */
final readonly class ApprovalRequestSchedule
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the creation and expiry timestamps assigned to an approval request.
   *
   * @access public
   *
   * @param DateTimeImmutable $expiresAt deadline after which the pending request is no longer actionable
   * @param DateTimeImmutable $createdAt time the request was created
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion
}
