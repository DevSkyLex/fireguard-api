<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Organization;

use DateTimeImmutable;

/**
 * Event OrganizationCreatedEvent.
 *
 * Raised when a new organization is created.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationCreatedEvent
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
   * OrganizationCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $name the organization name
   * @param string $ownerUserId the owner user ID
   */
  public function __construct(
    public string $organizationId,
    public string $name,
    public string $ownerUserId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
