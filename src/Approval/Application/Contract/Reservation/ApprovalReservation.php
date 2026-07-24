<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Reservation;

/**
 * Contract ApprovalReservation.
 *
 * The outcome of {@see \Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort::reservePending()}:
 * the pending request's identifier plus whether it was newly created by this
 * call (`isNew: false` means a concurrent/prior call already reserved the
 * pending row for this `(organization, action type, subject)` tuple — the
 * partial-unique idempotence guard).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalReservation
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the pending approval request identifier
   * @param bool $isNew whether this call created the row (false when it reused an already-pending one)
   */
  public function __construct(
    public string $id,
    public bool $isNew,
  ) {
  }
  // #endregion
}
