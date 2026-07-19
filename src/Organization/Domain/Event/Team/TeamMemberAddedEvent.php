<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Team;

use DateTimeImmutable;

/**
 * Event TeamMemberAddedEvent.
 *
 * Raised when an organization member is added to a team.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamMemberAddedEvent
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
   * TeamMemberAddedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $teamId the team ID
   * @param string $memberId the organization member ID
   * @param ?string $role the free-form membership label, when set
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    public string $memberId,
    public ?string $role = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
