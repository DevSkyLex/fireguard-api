<?php

declare(strict_types=1);

namespace Approval\Domain\Event\Request;

use DateTimeImmutable;

/**
 * Event ApprovalRejectedEvent.
 *
 * Raised when an approval request is rejected; the deferred action is never
 * executed.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalRejectedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

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
   * @param string $decisionByMemberId the rejecting member identifier
   * @param string $decisionByUserId the rejecting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actionType,
    public string $subjectId,
    public string $decisionByMemberId,
    public string $decisionByUserId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
