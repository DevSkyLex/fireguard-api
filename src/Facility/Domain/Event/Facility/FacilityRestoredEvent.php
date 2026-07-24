<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Facility;

use DateTimeImmutable;

/**
 * Event FacilityRestoredEvent.
 *
 * Raised when an archived facility is restored to active.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityRestoredEvent
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
   * FacilityRestoredEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $facilityId the facility ID
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
