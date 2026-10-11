<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Message;

use DateTimeImmutable;

/**
 * Contract AssistantMessageView.
 *
 * Read model for a persisted assistant message, shared across the
 * get-thread and ask-question use cases (mirrors
 * {@see \Messaging\Application\Contract\Message\MessageView}).
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantMessageView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the assistant message identifier
   * @param string $threadId the owning thread identifier
   * @param string $organizationId the owning organization identifier
   * @param string $role the message role (`user`|`assistant`)
   * @param string $body the message body
   * @param string $status the current generation status
   * @param ?string $errorCode the last failure code, once failed
   * @param ?int $tokenCount the generated token count, once complete
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $completedAt the completion/failure timestamp, once settled
   */
  public function __construct(
    public string $id,
    public string $threadId,
    public string $organizationId,
    public string $role,
    public string $body,
    public string $status,
    public ?string $errorCode,
    public ?int $tokenCount,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $completedAt,
    public ?string $attemptId = null,
    public int $attemptNumber = 0,
    public int $attemptSequence = 0,
    public ?DateTimeImmutable $attemptExpiresAt = null,
    public bool $canCancel = false,
    public bool $canRetry = false,
  ) {
  }
  // #endregion

}
