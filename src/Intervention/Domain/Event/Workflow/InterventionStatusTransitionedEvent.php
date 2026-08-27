<?php

declare(strict_types=1);

namespace Intervention\Domain\Event\Workflow;

use DateTimeImmutable;

/**
 * Event InterventionStatusTransitionedEvent.
 *
 * Raised after a successful intervention status transition commits —
 * every explicit transition applied through
 * `MutateInterventionWorkflowCommand` (`resource: 'intervention'`), and the
 * work-item-driven `planned -> in_progress` auto-start. Recorded in the
 * audit ledger as `intervention.status_transitioned`. `reviewNote` is
 * populated only when the target status is `changes_requested`, mirroring
 * the aggregate invariant that requires one for that transition.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionStatusTransitionedEvent
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
   * Initializes a new instance of the
   * InterventionStatusTransitionedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $interventionId the intervention identifier
   * @param int $interventionNumber the per-organization intervention number
   * @param ?string $actorUserId the acting user identifier, null for a system-driven transition
   * @param string $fromStatus the source status
   * @param string $toStatus the target status
   * @param ?string $reviewNote the review note, present only for a `changes_requested` target
   */
  public function __construct(
    public string $organizationId,
    public string $interventionId,
    public int $interventionNumber,
    public ?string $actorUserId,
    public string $fromStatus,
    public string $toStatus,
    public ?string $reviewNote = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
