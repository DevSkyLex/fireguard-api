<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Facility;

use DateTimeImmutable;

/**
 * Event FacilityCreatedEvent.
 *
 * Raised when a facility is created, whether through the resource-scoped
 * `POST` or the canonical `PUT` upsert (both dispatch the same
 * `CreateFacilityCommand`, so both emit exactly this event).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityCreatedEvent
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
   * FacilityCreatedEvent class.
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
