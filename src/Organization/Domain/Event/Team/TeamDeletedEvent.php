<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Team;

use DateTimeImmutable;

/**
 * Event TeamDeletedEvent.
 *
 * Raised when an organization team is deleted.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TeamDeletedEvent
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
   * TeamDeletedEvent class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param string $teamId the team ID
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
