<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Facility;

use DateTimeImmutable;

/**
 * Event FacilityArchivedEvent.
 *
 * Raised when a facility is archived. The archival guard has
 * already asserted that no active dependents remain (descendant
 * facilities, equipment, in-progress inspections), so consumers
 * never need to reconcile assigned assets.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityArchivedEvent
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
   * FacilityArchivedEvent class.
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
