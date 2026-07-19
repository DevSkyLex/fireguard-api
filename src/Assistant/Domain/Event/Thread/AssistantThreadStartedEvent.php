<?php

declare(strict_types=1);

namespace Assistant\Domain\Event\Thread;

use DateTimeImmutable;

/**
 * Event AssistantThreadStartedEvent.
 *
 * Raised when a member starts a new assistant thread.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantThreadStartedEvent
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
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the assistant thread identifier
   * @param string $memberId the starting member identifier
   */
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $memberId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
