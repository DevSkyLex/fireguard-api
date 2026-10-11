<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Event\Invitation;

use DateTimeImmutable;

/**
 * Event OrganizationInvitationRevokedEvent.
 *
 * Raised when a pending organization invitation is revoked.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationInvitationRevokedEvent
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
   * OrganizationInvitationRevokedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $invitationId the invitation ID
   * @param string $revokedByUserId the revoking user ID
   * @param string $reason why the invitation was revoked (manual, delivery_failed)
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $invitationId,
    public string $revokedByUserId,
    public string $reason = 'manual',
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
