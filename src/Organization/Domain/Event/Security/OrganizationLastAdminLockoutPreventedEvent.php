<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Security;

use DateTimeImmutable;

/**
 * Event OrganizationLastAdminLockoutPreventedEvent.
 *
 * Raised when the last-admin guard refuses an operation
 * that would leave the organization without any active
 * administrator.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationLastAdminLockoutPreventedEvent
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
   * OrganizationLastAdminLockoutPreventedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $attemptedAction the refused operation (remove_member, unassign_role, remove_members, update_role_permissions, delete_role)
   * @param string|null $memberId the targeted membership ID when applicable
   * @param string|null $roleId the targeted role ID when applicable
   */
  public function __construct(
    public string $organizationId,
    public string $attemptedAction,
    public ?string $memberId = null,
    public ?string $roleId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
