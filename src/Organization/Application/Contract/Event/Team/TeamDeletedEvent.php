<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Event\Team;

use DateTimeImmutable;

/**
 * Event TeamDeletedEvent.
 *
 * Raised when an organization team is deleted.
 *
 * @category Event
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
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $teamId,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
