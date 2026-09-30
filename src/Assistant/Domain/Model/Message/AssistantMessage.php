<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Message;

use Assistant\Domain\Exception\{AssistantAttemptConflictException, AssistantMessageIllegalStatusTransitionException, AssistantValidationException};
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
    private ?string $attemptId = null,
    private int $attemptNumber = 0,
    private int $attemptSequence = 0,
    private ?DateTimeImmutable $attemptExpiresAt = null,
    private ?string $questionMessageId = null,
    private ?float $temperature = null,
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
   * @return self the created user message
   *
   * @throws AssistantValidationException when the body is blank
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
   * @param RestoredAssistantMessageContent $content the persisted content and generation result
   * @param RestoredAssistantMessageAttempt $attempt the persisted generation attempt
   * @param RestoredAssistantMessageTimeline $timeline the persisted timestamps
   *
   * @return self the reconstituted assistant message
   */
  public static function reconstitute(
    AssistantMessageId $id,
    string $threadId,
    string $organizationId,
    AssistantMessageRole $role,
    RestoredAssistantMessageContent $content,
    RestoredAssistantMessageAttempt $attempt,
    RestoredAssistantMessageTimeline $timeline,
  ): self {
    return new self(
      id: $id,
      threadId: $threadId,
      organizationId: $organizationId,
      role: $role,
      body: $content->body,
      status: $content->status,
      errorCode: $content->errorCode,
      tokenCount: $content->tokenCount,
      createdAt: $timeline->createdAt,
      completedAt: $timeline->completedAt,
      attemptId: $attempt->attemptId,
      attemptNumber: $attempt->attemptNumber,
      attemptSequence: $attempt->attemptSequence,
      attemptExpiresAt: $attempt->attemptExpiresAt,
      questionMessageId: $attempt->questionMessageId,
      temperature: $attempt->temperature,
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
   * @throws AssistantMessageIllegalStatusTransitionException when not currently `pending`
   */
  public function markStreaming(): void
  {
    $this->assertTransition(AssistantMessageStatus::STREAMING);

    $this->status = AssistantMessageStatus::STREAMING;
    ++$this->attemptSequence;
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
    ++$this->attemptSequence;
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
    ++$this->attemptSequence;
  }

  /**
   * Method initializeAttempt.
   *
   * Establishes the first attempt for a new or legacy queued reply.
   *
   * @access public
   *
   * @param string $questionMessageId the identifier of the user message that prompted this reply
   * @param ?float $temperature the generation temperature when one was requested
   * @param DateTimeImmutable $now the time used to start the attempt expiry window
   *
   * @return void
   */
  public function initializeAttempt(string $questionMessageId, ?float $temperature, DateTimeImmutable $now): void
  {
    if (null !== $this->attemptId || !$this->isPending()) {
      return;
    }
    $this->questionMessageId = $questionMessageId;
    $this->temperature = $temperature;
    $this->attemptId = (string) $this->id;
    $this->attemptNumber = 1;
    $this->attemptExpiresAt = $now->modify('+5 minutes');
  }

  /** Method attemptId
   *
   * Returns the identifier of the current generation attempt, when set.
   *
   * @access public
   *
   * @return ?string the attempt identifier
   */
  public function attemptId(): ?string
  {
    return $this->attemptId;
  }

  /** Method attemptNumber
   *
   * Returns the number assigned to the current generation attempt.
   *
   * @access public
   *
   * @return int the attempt number
   */
  public function attemptNumber(): int
  {
    return $this->attemptNumber;
  }

  /** Method attemptSequence
   *
   * Returns the sequence counter for state and fragment updates.
   *
   * @access public
   *
   * @return int the attempt sequence
   */
  public function attemptSequence(): int
  {
    return $this->attemptSequence;
  }

  /** Method attemptExpiresAt
   *
   * Returns the current attempt expiration time, when initialized.
   *
   * @access public
   *
   * @return ?DateTimeImmutable the expiration time
   */
  public function attemptExpiresAt(): ?DateTimeImmutable
  {
    return $this->attemptExpiresAt;
  }

  /** Method questionMessageId
   *
   * Returns the user question associated with this reply, when known.
   *
   * @access public
   *
   * @return ?string the question message identifier
   */
  public function questionMessageId(): ?string
  {
    return $this->questionMessageId;
  }

  /** Method temperature
   *
   * Returns the generation temperature captured for this attempt.
   *
   * @access public
   *
   * @return ?float the generation temperature
   */
  public function temperature(): ?float
  {
    return $this->temperature;
  }

  /**
   * Method isExpired
   *
   * Checks whether the initialized attempt expiration has passed.
   *
   * @access public
   *
   * @param DateTimeImmutable $now time used for the comparison
   *
   * @return bool whether the attempt has expired
   */
  public function isExpired(DateTimeImmutable $now): bool
  {
    return null !== $this->attemptExpiresAt && $this->attemptExpiresAt <= $now;
  }

  /**
   * Method matchesAttempt
   *
   * Checks the supplied attempt identifier against the current or legacy message identifier.
   *
   * @access public
   *
   * @param string $attemptId identifier to check
   *
   * @return bool whether the identifier matches
   */
  public function matchesAttempt(string $attemptId): bool
  {
    return ($this->attemptId ?? (string) $this->id) === $attemptId;
  }

  /** Method canCancel
   *
   * Reports whether this assistant reply is not in a terminal state.
   *
   * @access public
   *
   * @return bool whether cancellation is currently allowed
   */
  public function canCancel(): bool
  {
    return AssistantMessageRole::ASSISTANT === $this->role && !$this->status->isTerminal();
  }

  /** Method canRetry
   *
   * Reports whether a failed or cancelled reply has an associated question.
   *
   * @access public
   *
   * @return bool whether retry is currently allowed
   */
  public function canRetry(): bool
  {
    return null !== $this->questionMessageId && (AssistantMessageStatus::FAILED === $this->status || AssistantMessageStatus::CANCELLED === $this->status);
  }

  /**
   * Method cancel
   *
   * Cancels the matching non-terminal attempt and records its completion time.
   *
   * @access public
   *
   * @param string $expectedAttemptId expected current attempt identifier
   * @param DateTimeImmutable $now cancellation time
   *
   * @return void
   *
   * @throws AssistantAttemptConflictException when the attempt is stale or cannot be cancelled
   */
  public function cancel(string $expectedAttemptId, DateTimeImmutable $now): void
  {
    if (!$this->matchesAttempt($expectedAttemptId)) {
      throw AssistantAttemptConflictException::stale();
    }
    if (AssistantMessageStatus::CANCELLED === $this->status) {
      return;
    }
    if (!$this->canCancel()) {
      throw AssistantAttemptConflictException::stale();
    }
    $this->status = AssistantMessageStatus::CANCELLED;
    $this->errorCode = null;
    $this->completedAt = $now;
    ++$this->attemptSequence;
  }

  /**
   * Method retry
   *
   * Resets this message for a new attempt when the expected attempt remains eligible.
   *
   * @access public
   *
   * @param string $expectedAttemptId expected current attempt identifier
   * @param string $newAttemptId identifier for the next attempt
   * @param DateTimeImmutable $now retry time
   *
   * @return void
   *
   * @throws AssistantAttemptConflictException when the attempt is stale or not retryable
   */
  public function retry(string $expectedAttemptId, string $newAttemptId, DateTimeImmutable $now): void
  {
    if (!$this->matchesAttempt($expectedAttemptId)) {
      throw AssistantAttemptConflictException::stale();
    }
    if (!$this->canRetry() && !($this->canCancel() && $this->isExpired($now) && null !== $this->questionMessageId)) {
      throw AssistantAttemptConflictException::notRetryable();
    }
    $this->attemptId = $newAttemptId;
    ++$this->attemptNumber;
    $this->attemptSequence = 0;
    $this->attemptExpiresAt = $now->modify('+5 minutes');
    $this->status = AssistantMessageStatus::PENDING;
    $this->body = '';
    $this->errorCode = null;
    $this->tokenCount = null;
    $this->completedAt = null;
  }

  /**
   * Method recordFragment
   *
   * Replaces the streaming body and advances its sequence counter.
   *
   * @access public
   *
   * @param string $body latest generated body fragment
   *
   * @return void
   *
   * @throws AssistantAttemptConflictException when the message is not streaming
   */
  public function recordFragment(string $body): void
  {
    if (AssistantMessageStatus::STREAMING !== $this->status) {
      throw AssistantAttemptConflictException::stale();
    }
    $this->body = $body;
    ++$this->attemptSequence;
  }

  /**
   * Method assertTransition
   *
   * Requires the current status to permit the requested transition.
   *
   * @access private
   * @since 1.0.0
   *
   * @param AssistantMessageStatus $target target status
   *
   * @return void
   *
   * @throws AssistantMessageIllegalStatusTransitionException when the transition is disallowed
   */
  private function assertTransition(AssistantMessageStatus $target): void
  {
    if (!$this->status->canTransitionTo($target)) {
      throw AssistantMessageIllegalStatusTransitionException::forTransition($this->status, $target, (string) $this->id);
    }
  }
  // #endregion
}
