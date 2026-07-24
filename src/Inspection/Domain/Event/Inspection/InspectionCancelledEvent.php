<?php

declare(strict_types=1);

namespace Inspection\Domain\Event\Inspection;

use DateTimeImmutable;

/**
 * Event InspectionCancelledEvent.
 *
 * Raised when an inspection is logically annulled
 * (draft/submitted -> cancelled, non-conformities preserved).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionCancelledEvent
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
   * InspectionCancelledEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $inspectionId the inspection ID
   * @param string $equipmentId the inspected equipment ID
   * @param string $previousStatus the status before cancellation (draft, submitted)
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $equipmentId,
    public string $previousStatus,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
