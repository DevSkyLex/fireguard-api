<?php

declare(strict_types=1);

namespace Assistant\Domain\Event\Message;

use DateTimeImmutable;

/**
 * Event AssistantQuestionAskedEvent.
 *
 * Raised when a member asks a question on an existing thread: the user
 * message and the placeholder `pending` assistant reply have both been
 * persisted, and generation has been (attempted to be) enqueued via
 * {@see \Assistant\Application\Port\Outbound\AssistantGenerationDispatcherPort}.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantQuestionAskedEvent
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
   * @param string $memberId the asking member identifier
   * @param string $userMessageId the persisted user message identifier
   * @param string $assistantMessageId the persisted pending assistant reply identifier
   */
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $memberId,
    public string $userMessageId,
    public string $assistantMessageId,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
