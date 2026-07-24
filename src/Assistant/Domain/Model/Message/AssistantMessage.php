<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use Assistant\Domain\Exception\{AssistantMessageIllegalStatusTransitionException, AssistantValidationException};
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus};
use DateTimeImmutable;

use function trim;

/**
 * Model AssistantMessage.
 *
 * One turn in an {@see \Assistant\Domain\Model\Thread\AssistantThread}: a
 * `user`-authored question (created directly {@see AssistantMessageStatus::COMPLETE})
 * or an `assistant`-authored reply that starts {@see AssistantMessageStatus::PENDING}
 * and advances through the generation state machine diagrammed on
 * {@see AssistantMessageStatus}. `status` is load-bearing: it is what lets a
 * Messenger retry on a partially-streamed reply REPLACE this same row in
 * place ({@see self::markComplete()}/{@see self::markFailed()} overwrite
 * `body`) instead of a second, duplicate assistant message ever being
 * appended for the same turn.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageId $id the assistant message identifier
   * @param string $threadId the owning thread identifier
   * @param string $organizationId the owning organization identifier (denormalized)
   * @param AssistantMessageRole $role the message role
   * @param string $body the message body
   * @param AssistantMessageStatus $status the current generation status
   * @param ?string $errorCode the last failure code, once failed
   * @param ?int $tokenCount the generated token count, once complete
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $completedAt the completion/failure timestamp, once settled
   */
  private function __construct(
    private readonly AssistantMessageId $id,
    private readonly string $threadId,
    private readonly string $organizationId,
    private readonly AssistantMessageRole $role,
    private string $body,
    private AssistantMessageStatus $status,
    private ?string $errorCode,
    private ?int $tokenCount,
    private readonly DateTimeImmutable $createdAt,
    private ?DateTimeImmutable $completedAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method askUser.
   *
   * @static
   *
   * Creates a `user`-authored message. A user message is authored, not
   * generated, so it is created already {@see AssistantMessageStatus::COMPLETE}.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageId $id the assistant message identifier
   * @param string $threadId the owning thread identifier
   * @param string $organizationId the owning organization identifier
   * @param string $body the question body
   * @param DateTimeImmutable $now the current time
   *
   * @throws AssistantValidationException when the body is blank
   *
   * @return self the created user message
   */
  public static function askUser(
    AssistantMessageId $id,
    string $threadId,
    string $organizationId,
    string $body,
    DateTimeImmutable $now,
  ): self {
    if ('' === trim($body)) {
      throw AssistantValidationException::blankBody();
    }

    return new self(
      id: $id,
      threadId: $threadId,
      organizationId: $organizationId,
      role: AssistantMessageRole::USER,
      body: $body,
      status: AssistantMessageStatus::COMPLETE,
      errorCode: null,
      tokenCount: null,
      createdAt: $now,
      completedAt: $now,
    );
  }

  /**
   * Method pendingReply.
   *
   * @static
   *
   * Creates the placeholder `assistant`-authored reply the moment it is
   * enqueued for generation, body empty and status
   * {@see AssistantMessageStatus::PENDING}.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageId $id the assistant message identifier
   * @param string $threadId the owning thread identifier
   * @param string $organizationId the owning organization identifier
   * @param DateTimeImmutable $now the current time
   *
   * @return self the created pending assistant reply
   */
  public static function pendingReply(
    AssistantMessageId $id,
    string $threadId,
    string $organizationId,
    DateTimeImmutable $now,
  ): self {
    return new self(
      id: $id,
      threadId: $threadId,
      organizationId: $organizationId,
      role: AssistantMessageRole::ASSISTANT,
      body: '',
      status: AssistantMessageStatus::PENDING,
      errorCode: null,
      tokenCount: null,
      createdAt: $now,
      completedAt: null,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes an assistant message from persisted state.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageId $id the assistant message identifier
   * @param string $threadId the owning thread identifier
   * @param string $organizationId the owning organization identifier
   * @param AssistantMessageRole $role the message role
   * @param string $body the message body
   * @param AssistantMessageStatus $status the current generation status
   * @param ?string $errorCode the last failure code, if any
   * @param ?int $tokenCount the generated token count, if any
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param ?DateTimeImmutable $completedAt the completion/failure timestamp, if any
   *
   * @return self the reconstituted assistant message
   */
  public static function reconstitute(
    AssistantMessageId $id,
    string $threadId,
    string $organizationId,
    AssistantMessageRole $role,
    string $body,
    AssistantMessageStatus $status,
    ?string $errorCode,
    ?int $tokenCount,
    DateTimeImmutable $createdAt,
    ?DateTimeImmutable $completedAt,
  ): self {
    return new self(
      id: $id,
      threadId: $threadId,
      organizationId: $organizationId,
      role: $role,
      body: $body,
      status: $status,
      errorCode: $errorCode,
      tokenCount: $tokenCount,
      createdAt: $createdAt,
      completedAt: $completedAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return AssistantMessageId the assistant message identifier
   */
  public function id(): AssistantMessageId
  {
    return $this->id;
  }

  /**
   * Method threadId.
   *
   * @since 1.0.0
   *
   * @return string the owning thread identifier
   */
  public function threadId(): string
  {
    return $this->threadId;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @return string the owning organization identifier
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method role.
   *
   * @since 1.0.0
   *
   * @return AssistantMessageRole the message role
   */
  public function role(): AssistantMessageRole
  {
    return $this->role;
  }

  /**
   * Method body.
   *
   * @since 1.0.0
   *
   * @return string the message body
   */
  public function body(): string
  {
    return $this->body;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   *
   * @return AssistantMessageStatus the current generation status
   */
  public function status(): AssistantMessageStatus
  {
    return $this->status;
  }

  /**
   * Method errorCode.
   *
   * @since 1.0.0
   *
   * @return ?string the last failure code, once failed
   */
  public function errorCode(): ?string
  {
    return $this->errorCode;
  }

  /**
   * Method tokenCount.
   *
   * @since 1.0.0
   *
   * @return ?int the generated token count, once complete
   */
  public function tokenCount(): ?int
  {
    return $this->tokenCount;
  }

  /**
   * Method createdAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method completedAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the completion/failure timestamp, once settled
   */
  public function completedAt(): ?DateTimeImmutable
  {
    return $this->completedAt;
  }

  /**
   * Method isPending.
   *
   * @since 1.0.0
   *
   * @return bool true when generation has not started streaming yet
   */
  public function isPending(): bool
  {
    return AssistantMessageStatus::PENDING === $this->status;
  }

  /**
   * Method markStreaming.
   *
   * Transitions `pending -> streaming`: the first token has arrived.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   *
   * @throws AssistantMessageIllegalStatusTransitionException when not currently `pending`
   */
  public function markStreaming(DateTimeImmutable $now): void
  {
    $this->assertTransition(AssistantMessageStatus::STREAMING);

    $this->status = AssistantMessageStatus::STREAMING;
  }

  /**
   * Method markComplete.
   *
   * Transitions `streaming -> complete`. `$body` REPLACES the current body
   * (never appends): a Messenger retry re-running a partially-streamed reply
   * must overwrite the same row rather than duplicate it.
   *
   * @since 1.0.0
   *
   * @param string $body the final, complete reply body
   * @param ?int $tokenCount the generated token count, if known
   * @param DateTimeImmutable $now the current time
   *
   * @throws AssistantMessageIllegalStatusTransitionException when not currently `streaming`
   */
  public function markComplete(string $body, ?int $tokenCount, DateTimeImmutable $now): void
  {
    $this->assertTransition(AssistantMessageStatus::COMPLETE);

    $this->status = AssistantMessageStatus::COMPLETE;
    $this->body = $body;
    $this->tokenCount = $tokenCount;
    $this->errorCode = null;
    $this->completedAt = $now;
  }

  /**
   * Method markFailed.
   *
   * Transitions to `failed`, legal from either `pending` (the backend never
   * produced a single token) or `streaming` (it failed mid-reply).
   *
   * @since 1.0.0
   *
   * @param string $errorCode the failure code
   * @param DateTimeImmutable $now the current time
   *
   * @throws AssistantMessageIllegalStatusTransitionException when already settled (`complete`/`failed`)
   */
  public function markFailed(string $errorCode, DateTimeImmutable $now): void
  {
    $this->assertTransition(AssistantMessageStatus::FAILED);

    $this->status = AssistantMessageStatus::FAILED;
    $this->errorCode = $errorCode;
    $this->completedAt = $now;
  }

  /**
   * Method assertTransition.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageStatus $target the candidate target status
   *
   * @throws AssistantMessageIllegalStatusTransitionException when the transition is not legal
   */
  private function assertTransition(AssistantMessageStatus $target): void
  {
    if (!$this->status->canTransitionTo($target)) {
      throw AssistantMessageIllegalStatusTransitionException::forTransition($this->status, $target, (string) $this->id);
    }
  }
  // #endregion
}
