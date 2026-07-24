<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Invitation;

use DateTimeImmutable;

/**
 * Event OrganizationInvitationSentEvent.
 *
 * Raised when an organization invitation is sent
 * to an email address (initial send or resend).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationInvitationSentEvent
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
   * OrganizationInvitationSentEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $invitationId the invitation ID
   * @param string $invitedEmail the invited email address
   * @param string $invitedByUserId the inviting user ID
   * @param bool $resend whether this is a resend of an existing invitation
   */
  public function __construct(
    public string $organizationId,
    public string $invitationId,
    public string $invitedEmail,
    public string $invitedByUserId,
    public bool $resend = false,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
