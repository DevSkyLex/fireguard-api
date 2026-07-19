<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Member;

use DateTimeImmutable;

/**
 * Event OrganizationMemberRemovedEvent.
 *
 * Raised when an organization member is removed
 * (membership deactivated).
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberRemovedEvent
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
   * OrganizationMemberRemovedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $memberId the membership ID
   * @param string $userId the user ID of the removed member
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $userId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
