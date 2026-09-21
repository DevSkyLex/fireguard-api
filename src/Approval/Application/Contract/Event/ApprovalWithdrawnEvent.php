<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Event;

use DateTimeImmutable;

/**
 * Event ApprovalWithdrawnEvent.
 *
 * Raised when an approval request is withdrawn; the deferred action is never
 * executed.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalWithdrawnEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $requestId the approval request identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param string $decisionByMemberId the withdrawing member identifier
   * @param string $decisionByUserId the withdrawing user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actionType,
    public string $subjectId,
    public string $decisionByMemberId,
    public string $decisionByUserId,
    public DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion
}
