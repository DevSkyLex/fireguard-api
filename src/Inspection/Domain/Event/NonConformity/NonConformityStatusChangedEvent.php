<?php

declare(strict_types=1);

namespace Inspection\Domain\Event\NonConformity;

use DateTimeImmutable;

/**
 * Event NonConformityStatusChangedEvent.
 *
 * Raised when a non-conformity status changes
 * (open, in_progress, done, waived — done/waived resolve it).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityStatusChangedEvent
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
   * NonConformityStatusChangedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $inspectionId the inspection ID
   * @param string $nonConformityId the non-conformity ID
   * @param string $previousStatus the status before the change
   * @param string $status the status after the change
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $nonConformityId,
    public string $previousStatus,
    public string $status,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
