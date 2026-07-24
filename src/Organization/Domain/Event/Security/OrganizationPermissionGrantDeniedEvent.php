<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Security;

use DateTimeImmutable;

/**
 * Event OrganizationPermissionGrantDeniedEvent.
 *
 * Raised when the no-privilege-escalation guard refuses
 * a role write that would grant permissions beyond the
 * actor's own effective set.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationPermissionGrantDeniedEvent
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
   * OrganizationPermissionGrantDeniedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $actorUserId the user attempting the grant
   * @param string $deniedPermission the permission that was refused
   * @param string $context the guarded surface (grant_permissions or assign_roles)
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $deniedPermission,
    public string $context,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
