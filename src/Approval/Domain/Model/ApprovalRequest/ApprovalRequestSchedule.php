<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use DateTimeImmutable;

/** The submission time and expiry deadline of an approval request. */
final readonly class ApprovalRequestSchedule
{
  public function __construct(
    public DateTimeImmutable $expiresAt,
    public DateTimeImmutable $createdAt,
  ) {
  }
}
