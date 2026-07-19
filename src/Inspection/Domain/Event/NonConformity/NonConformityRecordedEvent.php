<?php

declare(strict_types=1);

namespace Inspection\Domain\Event\NonConformity;

use DateTimeImmutable;

/**
 * Event NonConformityRecordedEvent.
 *
 * Raised when a deficiency (non-conformity) is recorded
 * against an inspection.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityRecordedEvent
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
   * NonConformityRecordedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $inspectionId the inspection ID
   * @param string $nonConformityId the non-conformity ID
   * @param string $severity the severity (low, medium, high, critical)
   */
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $nonConformityId,
    public string $severity,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
