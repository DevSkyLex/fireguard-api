<?php

declare(strict_types=1);

namespace Approval\Domain\Model\ApprovalRequest;

use Approval\Domain\Exception\ApprovalRequestNotPendingException;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use DateTimeImmutable;

/**
 * Model ApprovalRequest.
 *
 * A pending (or decided) four-eyes approval request created by
 * {@see \Approval\Application\Service\ApprovalGate} when an org-configured
 * action type requires a second authorized member's decision. Carries the
 * deferred action's payload (the owning module's original command
 * parameters) so {@see \Approval\Application\Service\ApprovalActionExecutorRegistry}
 * can re-dispatch it verbatim on approval. Mirrors
 * {@see \Webhook\Domain\Model\Subscription\WebhookSubscription}: a mutable
 * aggregate with `create()`/`reconstitute()` factories.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestId $id the approval request identifier
   * @param string $organizationId the owning organization identifier (denormalized)
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param ApprovalStatus $status the current status
   * @param string $requestedByMemberId the requesting member identifier
   * @param string $requestedByUserId the requesting user identifier
   * @param ?string $decisionByMemberId the deciding member identifier, once decided
   * @param ?string $decisionByUserId the deciding user identifier, once decided
   * @param ?string $decisionNote the free-form decision note
   * @param array<string, mixed> $payload the deferred action's payload
   * @param DateTimeImmutable $expiresAt the expiry deadline
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?DateTimeImmutable $decidedAt the decision timestamp, once decided
   * @param ?DateTimeImmutable $executedAt the deferred action execution timestamp, once executed
   * @param ?string $executionError the last execution/cancellation error, if any
   */
  private function __construct(
    private readonly ApprovalRequestId $id,
    private readonly string $organizationId,
    private readonly string $actionType,
    private readonly string $subjectId,
    private ApprovalStatus $status,
    private readonly string $requestedByMemberId,
    private readonly string $requestedByUserId,
    private ?string $decisionByMemberId,
    private ?string $decisionByUserId,
    private ?string $decisionNote,
    private readonly array $payload,
    private readonly DateTimeImmutable $expiresAt,
    private readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?DateTimeImmutable $decidedAt,
    private ?DateTimeImmutable $executedAt,
    private ?string $executionError,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new pending approval request.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestId $id the approval request identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param string $requestedByMemberId the requesting member identifier
   * @param string $requestedByUserId the requesting user identifier
   * @param array<string, mixed> $payload the deferred action's payload
   * @param DateTimeImmutable $expiresAt the expiry deadline
   * @param DateTimeImmutable $now the current time
   *
   * @return self the created approval request
   */
  public static function create(
    ApprovalRequestId $id,
    string $organizationId,
    string $actionType,
    string $subjectId,
    string $requestedByMemberId,
    string $requestedByUserId,
    array $payload,
    DateTimeImmutable $expiresAt,
    DateTimeImmutable $now,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      actionType: $actionType,
      subjectId: $subjectId,
      status: ApprovalStatus::PENDING,
      requestedByMemberId: $requestedByMemberId,
      requestedByUserId: $requestedByUserId,
      decisionByMemberId: null,
      decisionByUserId: null,
      decisionNote: null,
      payload: $payload,
      expiresAt: $expiresAt,
      createdAt: $now,
      updatedAt: $now,
      decidedAt: null,
      executedAt: null,
      executionError: null,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes an approval request from persisted state.
   *
   * @since 1.0.0
   *
   * @param ApprovalRequestId $id the approval request identifier
   * @param string $organizationId the owning organization identifier
   * @param string $actionType the regulated action type
   * @param string $subjectId the acted-upon subject identifier
   * @param ApprovalStatus $status the current status
   * @param string $requestedByMemberId the requesting member identifier
   * @param string $requestedByUserId the requesting user identifier
   * @param ?string $decisionByMemberId the deciding member identifier
   * @param ?string $decisionByUserId the deciding user identifier
   * @param ?string $decisionNote the free-form decision note
   * @param array<string, mixed> $payload the deferred action's payload
   * @param DateTimeImmutable $expiresAt the expiry deadline
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?DateTimeImmutable $decidedAt the decision timestamp
   * @param ?DateTimeImmutable $executedAt the execution timestamp
   * @param ?string $executionError the last execution/cancellation error
   *
   * @return self the reconstituted approval request
   */
  public static function reconstitute(
    ApprovalRequestId $id,
    string $organizationId,
    string $actionType,
    string $subjectId,
    ApprovalStatus $status,
    string $requestedByMemberId,
    string $requestedByUserId,
    ?string $decisionByMemberId,
    ?string $decisionByUserId,
    ?string $decisionNote,
    array $payload,
    DateTimeImmutable $expiresAt,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?DateTimeImmutable $decidedAt,
    ?DateTimeImmutable $executedAt,
    ?string $executionError,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      actionType: $actionType,
      subjectId: $subjectId,
      status: $status,
      requestedByMemberId: $requestedByMemberId,
      requestedByUserId: $requestedByUserId,
      decisionByMemberId: $decisionByMemberId,
      decisionByUserId: $decisionByUserId,
      decisionNote: $decisionNote,
      payload: $payload,
      expiresAt: $expiresAt,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      decidedAt: $decidedAt,
      executedAt: $executedAt,
      executionError: $executionError,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return ApprovalRequestId the approval request identifier
   */
  public function id(): ApprovalRequestId
  {
    return $this->id;
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
   * Method actionType.
   *
   * @since 1.0.0
   *
   * @return string the regulated action type
   */
  public function actionType(): string
  {
    return $this->actionType;
  }

  /**
   * Method subjectId.
   *
   * @since 1.0.0
   *
   * @return string the acted-upon subject identifier
   */
  public function subjectId(): string
  {
    return $this->subjectId;
  }

  /**
   * Method status.
   *
   * @since 1.0.0
   *
   * @return ApprovalStatus the current status
   */
  public function status(): ApprovalStatus
  {
    return $this->status;
  }

  /**
   * Method requestedByMemberId.
   *
   * @since 1.0.0
   *
   * @return string the requesting member identifier
   */
  public function requestedByMemberId(): string
  {
    return $this->requestedByMemberId;
  }

  /**
   * Method requestedByUserId.
   *
   * @since 1.0.0
   *
   * @return string the requesting user identifier
   */
  public function requestedByUserId(): string
  {
    return $this->requestedByUserId;
  }

  /**
   * Method decisionByMemberId.
   *
   * @since 1.0.0
   *
   * @return ?string the deciding member identifier, once decided
   */
  public function decisionByMemberId(): ?string
  {
    return $this->decisionByMemberId;
  }

  /**
   * Method decisionByUserId.
   *
   * @since 1.0.0
   *
   * @return ?string the deciding user identifier, once decided
   */
  public function decisionByUserId(): ?string
  {
    return $this->decisionByUserId;
  }

  /**
   * Method decisionNote.
   *
   * @since 1.0.0
   *
   * @return ?string the free-form decision note
   */
  public function decisionNote(): ?string
  {
    return $this->decisionNote;
  }

  /**
   * Method payload.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the deferred action's payload
   */
  public function payload(): array
  {
    return $this->payload;
  }

  /**
   * Method expiresAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the expiry deadline
   */
  public function expiresAt(): DateTimeImmutable
  {
    return $this->expiresAt;
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
   * Method updatedAt.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the last update timestamp
   */
  public function updatedAt(): DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method decidedAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the decision timestamp, once decided
   */
  public function decidedAt(): ?DateTimeImmutable
  {
    return $this->decidedAt;
  }

  /**
   * Method executedAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the execution timestamp, once executed
   */
  public function executedAt(): ?DateTimeImmutable
  {
    return $this->executedAt;
  }

  /**
   * Method executionError.
   *
   * @since 1.0.0
   *
   * @return ?string the last execution/cancellation error, if any
   */
  public function executionError(): ?string
  {
    return $this->executionError;
  }

  /**
   * Method isPending.
   *
   * @since 1.0.0
   *
   * @return bool true when the request is still awaiting a decision
   */
  public function isPending(): bool
  {
    return ApprovalStatus::PENDING === $this->status;
  }

  /**
   * Method approve.
   *
   * Records the approval decision. Called BEFORE the deferred action is
   * (re-)executed is wrong — the caller must execute the deferred action
   * first (while the request is still `pending`) and only call this once
   * execution succeeded, so a request never reads as `approved` while its
   * action is still unapplied.
   *
   * @since 1.0.0
   *
   * @param string $decisionByMemberId the approving member identifier
   * @param string $decisionByUserId the approving user identifier
   * @param ?string $decisionNote the free-form decision note
   * @param DateTimeImmutable $now the current time
   */
  public function approve(string $decisionByMemberId, string $decisionByUserId, ?string $decisionNote, DateTimeImmutable $now): void
  {
    $this->assertPending();

    $this->status = ApprovalStatus::APPROVED;
    $this->decisionByMemberId = $decisionByMemberId;
    $this->decisionByUserId = $decisionByUserId;
    $this->decisionNote = $decisionNote;
    $this->decidedAt = $now;
    $this->touch($now);
  }

  /**
   * Method reject.
   *
   * Records a rejection decision. The deferred action is never executed.
   *
   * @since 1.0.0
   *
   * @param string $decisionByMemberId the rejecting member identifier
   * @param string $decisionByUserId the rejecting user identifier
   * @param ?string $decisionNote the free-form decision note
   * @param DateTimeImmutable $now the current time
   */
  public function reject(string $decisionByMemberId, string $decisionByUserId, ?string $decisionNote, DateTimeImmutable $now): void
  {
    $this->assertPending();

    $this->status = ApprovalStatus::REJECTED;
    $this->decisionByMemberId = $decisionByMemberId;
    $this->decisionByUserId = $decisionByUserId;
    $this->decisionNote = $decisionNote;
    $this->decidedAt = $now;
    $this->touch($now);
  }

  /**
   * Method cancel.
   *
   * Cancels a still-pending request, recording the reason in
   * {@see self::executionError()}. Used by the approve flow when the
   * deferred action can no longer be applied (the subject changed state
   * since the request was created).
   *
   * @since 1.0.0
   *
   * @param string $reason the cancellation reason
   * @param DateTimeImmutable $now the current time
   */
  public function cancel(string $reason, DateTimeImmutable $now): void
  {
    $this->assertPending();

    $this->status = ApprovalStatus::CANCELLED;
    $this->executionError = $reason;
    $this->touch($now);
  }

  /**
   * Method expire.
   *
   * Transitions a stale pending request to `expired`. Called only by the
   * scheduled sweep.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  public function expire(DateTimeImmutable $now): void
  {
    $this->assertPending();

    $this->status = ApprovalStatus::EXPIRED;
    $this->touch($now);
  }

  /**
   * Method markExecuted.
   *
   * Records that the deferred action has been (re-)executed.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  public function markExecuted(DateTimeImmutable $now): void
  {
    $this->executedAt = $now;
    $this->executionError = null;
    $this->touch($now);
  }

  /**
   * Method markExecutionFailed.
   *
   * Records a deferred action execution failure without changing status —
   * callers needing a status transition use {@see self::cancel()} instead.
   *
   * @since 1.0.0
   *
   * @param string $error the failure reason
   * @param DateTimeImmutable $now the current time
   */
  public function markExecutionFailed(string $error, DateTimeImmutable $now): void
  {
    $this->executionError = $error;
    $this->touch($now);
  }

  /**
   * Method assertPending.
   *
   * @since 1.0.0
   */
  private function assertPending(): void
  {
    if (!$this->isPending()) {
      throw ApprovalRequestNotPendingException::withId((string) $this->id);
    }
  }

  /**
   * Method touch.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  private function touch(DateTimeImmutable $now): void
  {
    $this->updatedAt = $now;
  }
  // #endregion
}
