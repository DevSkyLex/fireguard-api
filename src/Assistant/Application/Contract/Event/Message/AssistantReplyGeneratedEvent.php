<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Event\Message;

use DateTimeImmutable;

/**
 * Event AssistantReplyGeneratedEvent.
 *
 * Raised by
 * the owning reply-generation use case
 * once a pending assistant reply has successfully reached
 * `complete`. Not
 * raised on a `failed` outcome — that is observable via the message's own
 * `status`/`errorCode` and the handler's log entry.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantReplyGeneratedEvent
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
   * @param string $assistantMessageId the completed assistant reply identifier
   * @param ?int $tokenCount the generated token count, if known
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $assistantMessageId,
    public ?int $tokenCount,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
