<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use DateTimeImmutable;

/** Persisted generation attempt and its originating question. */
final readonly class RestoredAssistantMessageAttempt
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores the attempt number, sequence, expiry, question link, and generation temperature for an assistant message.
   *
   * @access public
   *
   * @param ?string $attemptId identifier of the current generation attempt, when one exists
   * @param int $attemptNumber attempt ordinal exposed to the conversation flow
   * @param int $attemptSequence monotonic sequence used to distinguish attempts
   * @param ?DateTimeImmutable $attemptExpiresAt deadline for the current attempt, when scheduled
   * @param ?string $questionMessageId user message that prompted this assistant reply
   * @param ?float $temperature sampling temperature used for generation, when recorded
   *
   * @return void
   */
  public function __construct(
    public ?string $attemptId = null,
    public int $attemptNumber = 0,
    public int $attemptSequence = 0,
    public ?DateTimeImmutable $attemptExpiresAt = null,
    public ?string $questionMessageId = null,
    public ?float $temperature = null,
  ) {
  }
  // #endregion
}
