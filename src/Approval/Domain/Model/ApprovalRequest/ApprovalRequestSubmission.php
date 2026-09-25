<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

/** The requester and deferred action supplied when an approval is submitted. */
final readonly class ApprovalRequestSubmission
{
  /**
   * @param array<string, mixed> $payload
   */
  public function __construct(
    public string $requestedByMemberId,
    public string $requestedByUserId,
    public array $payload,
  ) {
  }
}
