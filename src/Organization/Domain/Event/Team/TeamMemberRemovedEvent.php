<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Team;

use DateTimeImmutable;

/**
 * Event TeamMemberRemovedEvent.
 *
 * Raised when an organization member is removed from a team.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamMemberRemovedEvent
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
   * TeamMemberRemovedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $teamId the team ID
   * @param string $memberId the organization member ID
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    public string $memberId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
