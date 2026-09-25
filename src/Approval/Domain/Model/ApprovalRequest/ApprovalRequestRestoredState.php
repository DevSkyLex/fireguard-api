<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use Approval\Domain\ValueObject\ApprovalStatus;
use DateTimeImmutable;

/** Persisted approval state supplied by the mapper without changing stored fields. */
final readonly class ApprovalRequestRestoredState
{
  public function __construct(
    public ApprovalRequestCreation $creation,
    public ApprovalStatus $status,
    public DateTimeImmutable $updatedAt,
    public ApprovalRequestResolution $resolution,
  ) {
  }
}
