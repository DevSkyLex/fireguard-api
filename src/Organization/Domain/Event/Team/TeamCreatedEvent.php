<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Team;

use DateTimeImmutable;

/**
 * Event TeamCreatedEvent.
 *
 * Raised when an organization team is created.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamCreatedEvent
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
   * TeamCreatedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $teamId the team ID
   * @param string $name the team name
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
