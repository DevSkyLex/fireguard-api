<?php

declare(strict_types=1);

namespace Approval\Domain\Event\Request;

use DateTimeImmutable;

/**
 * Event ApprovalRequestedEvent.
 *
 * Raised when the approval gate defers a regulated action and creates a new
 * pending {@see \Approval\Domain\Model\ApprovalRequest\ApprovalRequest}.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalRequestedEvent
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
   * @param string $requestedByMemberId the requesting member identifier
   * @param string $requestedByUserId the requesting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actionType,
    public string $subjectId,
    public string $requestedByMemberId,
    public string $requestedByUserId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
