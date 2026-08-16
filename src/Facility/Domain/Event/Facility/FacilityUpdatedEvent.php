<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Facility;

use DateTimeImmutable;

/**
 * Event FacilityUpdatedEvent.
 *
 * Raised when a facility's descriptive attributes (type, name, code,
 * address, coordinates, metadata) change. Lifecycle transitions
 * (archive/restore) and hierarchy changes (move) are covered by their own
 * dedicated events and are never listed here. `changedFields` carries only
 * the field NAMES that actually changed, never their values — the ledger
 * must not become a second copy of potentially sensitive facility data
 * (address, metadata) nor grow noisy with every partial patch's payload.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityUpdatedEvent
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
   * FacilityUpdatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $facilityId the facility ID
   * @param list<string> $changedFields the field names that changed
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public array $changedFields,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
