<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use DateTimeImmutable;

/** Persisted decision and deferred execution outcome. */
final readonly class ApprovalRequestResolution
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Captures the actor decision and the result of executing an approved action.
   *
   * @access public
   *
   * @param ?string $decisionByMemberId organization member who made the decision, when available
   * @param ?string $decisionByUserId user account that made the decision, when available
   * @param ?string $decisionNote optional explanation recorded with the decision
   * @param ?DateTimeImmutable $decidedAt time the decision was recorded, when decided
   * @param ?DateTimeImmutable $executedAt time the approved action completed, when executed
   * @param ?string $executionError failure detail recorded when action execution failed
   *
   * @return void
   */
  public function __construct(
    public ?string $decisionByMemberId,
    public ?string $decisionByUserId,
    public ?string $decisionNote,
    public ?DateTimeImmutable $decidedAt,
    public ?DateTimeImmutable $executedAt,
    public ?string $executionError,
  ) {
  }
  // #endregion
}
