<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Facility;

use DateTimeImmutable;

/**
 * Event FacilityMovedEvent.
 *
 * Raised when a facility is moved under another parent
 * (or to the root) in the location hierarchy.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMovedEvent
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
   * FacilityMovedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $facilityId the facility ID
   * @param string|null $previousParentFacilityId the parent before the move (null = root)
   * @param string|null $newParentFacilityId the parent after the move (null = root)
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $previousParentFacilityId,
    public ?string $newParentFacilityId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
