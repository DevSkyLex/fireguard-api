<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Organization;

use DateTimeImmutable;

/**
 * Event OrganizationSuspendedEvent.
 *
 * Raised when an organization is deactivated (suspended)
 * through the settings surface.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSuspendedEvent
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
   * OrganizationSuspendedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   */
  public function __construct(
    public string $organizationId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
