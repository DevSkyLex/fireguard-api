<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use DateTimeImmutable;

/** Persisted decision and deferred execution outcome. */
final readonly class ApprovalRequestResolution
{
  public function __construct(
    public ?string $decisionByMemberId,
    public ?string $decisionByUserId,
    public ?string $decisionNote,
    public ?DateTimeImmutable $decidedAt,
    public ?DateTimeImmutable $executedAt,
    public ?string $executionError,
  ) {
  }
}
