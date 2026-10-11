<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Event\Member;

use DateTimeImmutable;

/**
 * Event OrganizationMemberAddedEvent.
 *
 * Raised when a user becomes an active member
 * of an organization.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationMemberAddedEvent
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
   * OrganizationMemberAddedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $memberId the membership ID
   * @param string $userId the user ID of the new member
   * @param list<string> $roleIds the role IDs granted with the membership
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $memberId,
    public string $userId,
    public array $roleIds = [],
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
