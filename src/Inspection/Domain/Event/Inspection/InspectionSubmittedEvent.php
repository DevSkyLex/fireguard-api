<?php

declare(strict_types=1);

namespace Inspection\Domain\Event\Inspection;

use DateTimeImmutable;

/**
 * Event InspectionSubmittedEvent.
 *
 * Raised when an inspection is submitted (draft -> submitted).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionSubmittedEvent
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
   * InspectionSubmittedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $inspectionId the inspection ID
   * @param string $equipmentId the inspected equipment ID
   * @param string $result the inspection result (pass, fail, partial)
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $equipmentId,
    public string $result,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
