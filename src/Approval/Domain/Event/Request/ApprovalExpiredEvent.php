<?php

declare(strict_types=1);

namespace Approval\Domain\Event\Request;

use DateTimeImmutable;

/**
 * Event ApprovalExpiredEvent.
 *
 * Raised by the scheduled sweep when a pending approval request is left
 * undecided past its `expiresAt` deadline.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalExpiredEvent
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
   */
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actionType,
    public string $subjectId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
