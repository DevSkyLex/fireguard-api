<?php

declare(strict_types=1);

namespace Facility\Domain\Event\Facility;

use DateTimeImmutable;

/**
 * Event FacilitySubtreeDuplicatedEvent.
 *
 * Raised once, after the durable save, when a facility subtree has been
 * duplicated into a new, independent branch of the location hierarchy.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilitySubtreeDuplicatedEvent
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
   * FacilitySubtreeDuplicatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $sourceFacilityId the duplicated subtree's source facility ID
   * @param string $newRootFacilityId the new subtree's root facility ID
   * @param int $nodeCount the number of facility nodes created by the duplication (root included)
   */
  public function __construct(
    public string $organizationId,
    public string $sourceFacilityId,
    public string $newRootFacilityId,
    public int $nodeCount,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
