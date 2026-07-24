<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Role;

use DateTimeImmutable;

/**
 * Event OrganizationRoleDeletedEvent.
 *
 * Raised when an organization-scoped role is deleted.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleDeletedEvent
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
   * OrganizationRoleDeletedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $roleId the role ID
   * @param string $roleName the role name
   */
  public function __construct(
    public string $organizationId,
    public string $roleId,
    public string $roleName,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
