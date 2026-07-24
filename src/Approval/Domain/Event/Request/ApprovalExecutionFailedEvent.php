<?php

declare(strict_types=1);

namespace Approval\Domain\Event\Request;

use DateTimeImmutable;

/**
 * Event ApprovalExecutionFailedEvent.
 *
 * Raised when an approved request's deferred action can no longer be
 * re-executed because its subject changed state since the request was
 * created; the request is transitioned to `cancelled` alongside this event.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalExecutionFailedEvent
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
   * @param string $error the failure reason
   * @param string $decisionByUserId the deciding user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actionType,
    public string $subjectId,
    public string $error,
    public string $decisionByUserId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
