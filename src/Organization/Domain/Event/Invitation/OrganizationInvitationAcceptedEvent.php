<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Invitation;

use DateTimeImmutable;

/**
 * Event OrganizationInvitationAcceptedEvent.
 *
 * Raised when an organization invitation is accepted
 * and the invitee becomes a member.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationInvitationAcceptedEvent
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
   * OrganizationInvitationAcceptedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $invitationId the invitation ID
   * @param string $memberId the created membership ID
   * @param string $userId the accepting user ID
   * @param string $userEmail the accepting user email
   * @param list<string> $roleIds the member role IDs after acceptance
   */
  public function __construct(
    public string $organizationId,
    public string $invitationId,
    public string $memberId,
    public string $userId,
    public string $userEmail,
    public array $roleIds = [],
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
