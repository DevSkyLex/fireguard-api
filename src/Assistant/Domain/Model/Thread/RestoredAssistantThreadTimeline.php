<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Thread;

use DateTimeImmutable;

/** Persisted timestamps of a member-private assistant thread. */
final readonly class RestoredAssistantThreadTimeline
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores the timestamps used to order a member-private assistant thread and its activity.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt time the thread was created
   * @param DateTimeImmutable $updatedAt time the thread state was last changed
   * @param ?DateTimeImmutable $lastMessageAt time of the most recent message, when one exists
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $lastMessageAt,
  ) {
  }
  // #endregion
}
