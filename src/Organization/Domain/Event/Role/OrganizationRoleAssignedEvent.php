<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Role;

use DateTimeImmutable;

/**
 * Event OrganizationRoleAssignedEvent.
 *
 * Raised when a role is assigned to an organization member.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleAssignedEvent
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
   * OrganizationRoleAssignedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $memberId the membership ID
   * @param string $roleId the role ID
   * @param string $roleName the role name
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $roleId,
    public string $roleName,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
