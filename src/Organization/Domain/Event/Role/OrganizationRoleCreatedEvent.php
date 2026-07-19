<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Role;

use DateTimeImmutable;

/**
 * Event OrganizationRoleCreatedEvent.
 *
 * Raised when an organization-scoped role is created.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleCreatedEvent
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
   * OrganizationRoleCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $roleId the role ID
   * @param string $roleName the role name
   * @param list<string> $permissions the granted permissions
   */
  public function __construct(
    public string $organizationId,
    public string $roleId,
    public string $roleName,
    public array $permissions,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
