<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Team;

use DateTimeImmutable;

/**
 * Event TeamUpdatedEvent.
 *
 * Raised when an organization team's name or description is updated.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamUpdatedEvent
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
   * TeamUpdatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $teamId the team ID
   * @param string $name the team name after update
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    public string $name,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
