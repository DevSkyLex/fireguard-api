<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use DateTimeImmutable;

/** Persisted timestamps of an assistant message. */
final readonly class RestoredAssistantMessageTimeline
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores the creation and optional completion timestamps of an assistant message.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt time the message was created
   * @param ?DateTimeImmutable $completedAt time processing completed, when the message is terminal
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $completedAt,
  ) {
  }
  // #endregion
}
