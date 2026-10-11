<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Event\Organization;

use DateTimeImmutable;

/**
 * Event OrganizationOwnershipTransferredEvent.
 *
 * Raised when an organization's ownership moves from one
 * user to another.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationOwnershipTransferredEvent
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
   * OrganizationOwnershipTransferredEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $previousOwnerUserId the user ID of the previous owner
   * @param string $newOwnerUserId the user ID of the new owner
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $previousOwnerUserId,
    public string $newOwnerUserId,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
