<?php

declare(strict_types=1);

namespace Assistant\Domain\Event\Message;

use DateTimeImmutable;

/**
 * Event AssistantReplyGeneratedEvent.
 *
 * Raised by
 * {@see \Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyHandler}
 * once a pending assistant reply has successfully reached
 * {@see \Assistant\Domain\ValueObject\AssistantMessageStatus::COMPLETE}. Not
 * raised on a `failed` outcome — that is observable via the message's own
 * `status`/`errorCode` and the handler's log entry.
 *
 * @category Event
 *
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
   */
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $assistantMessageId,
    public ?int $tokenCount,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
