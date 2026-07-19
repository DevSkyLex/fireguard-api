<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Organization;

use DateTimeImmutable;

/**
 * Event OrganizationRestoredEvent.
 *
 * Raised when an organization is reactivated
 * from an archived or suspended state.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRestoredEvent
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
   * OrganizationRestoredEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $previousStatus the status before reactivation
   */
  public function __construct(
    public string $organizationId,
    public string $previousStatus,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
