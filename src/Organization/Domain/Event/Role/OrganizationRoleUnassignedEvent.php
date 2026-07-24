<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Role;

use DateTimeImmutable;

/**
 * Event OrganizationRoleUnassignedEvent.
 *
 * Raised when a role is removed from an organization member.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleUnassignedEvent
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
   * OrganizationRoleUnassignedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $memberId the membership ID
   * @param string $roleId the role ID
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $roleId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
