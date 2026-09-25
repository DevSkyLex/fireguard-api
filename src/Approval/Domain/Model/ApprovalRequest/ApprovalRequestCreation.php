<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use Approval\Domain\ValueObject\ApprovalRequestId;

/** Immutable data shared by a new approval and its atomic pending reservation. */
final readonly class ApprovalRequestCreation
{
  public function __construct(
    public ApprovalRequestId $id,
    public string $organizationId,
    public string $actionType,
    public string $subjectId,
    public ApprovalRequestSubmission $submission,
    public ApprovalRequestSchedule $schedule,
  ) {
  }
}
