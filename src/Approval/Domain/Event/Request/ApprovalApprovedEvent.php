<?php

declare(strict_types=1);

namespace Approval\Domain\Event\Request;

use DateTimeImmutable;

/**
 * Event ApprovalApprovedEvent.
 *
 * Raised when an approval request is approved and its deferred action has
 * been (re-)executed successfully.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalApprovedEvent
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
   * @param string $decisionByMemberId the approving member identifier
   * @param string $decisionByUserId the approving user identifier
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
