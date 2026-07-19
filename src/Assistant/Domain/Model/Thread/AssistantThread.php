<?php

declare(strict_types=1);

namespace Assistant\Domain\Model\Thread;

use Assistant\Domain\ValueObject\AssistantThreadId;
use DateTimeImmutable;

/**
 * Model AssistantThread.
 *
 * A member-private AI-assistant conversation, scoped to one organization and
 * one member: there is no shared/organization-wide assistant thread in this
 * design, so a thread is never visible to any member other than the one who
 * started it. Mirrors {@see \Approval\Domain\Model\ApprovalRequest\ApprovalRequest}:
 * a mutable aggregate with `start()`/`reconstitute()` factories.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThread
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadId $id the assistant thread identifier
   * @param string $organizationId the owning organization identifier (denormalized)
   * @param string $memberId the owning member identifier
   * @param ?string $title the thread's display title, if any
   * @param ?string $model the model identifier used for this thread, once known
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?DateTimeImmutable $lastMessageAt the last message timestamp, if any
   */
  private function __construct(
    private readonly AssistantThreadId $id,
    private readonly string $organizationId,
    private readonly string $memberId,
    private readonly ?string $title,
    private readonly ?string $model,
    private readonly DateTimeImmutable $createdAt,
    private DateTimeImmutable $updatedAt,
    private ?DateTimeImmutable $lastMessageAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method start.
   *
   * @static
   *
   * Starts a new assistant thread for a member. `$model` is this thread's
   * ONLY tenant-controlled model override (validated against
   * `OLLAMA_ALLOWED_MODELS` by {@see \Assistant\Domain\Service\AssistantModelPolicy}
   * before this is ever called — see `StartAssistantThreadHandler`); when
   * null, generation later falls back to the operator's `OLLAMA_DEFAULT_MODEL`.
   * Added as the LAST parameter (not inserted before `$now`) so every
   * existing positional-argument call site stays valid.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadId $id the assistant thread identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the owning member identifier
   * @param ?string $title the thread's display title, if provided
   * @param DateTimeImmutable $now the current time
   * @param ?string $model the tenant-selected model override, if any and allowed
   *
   * @return self the started assistant thread
   */
  public static function start(
    AssistantThreadId $id,
    string $organizationId,
    string $memberId,
    ?string $title,
    DateTimeImmutable $now,
    ?string $model = null,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      memberId: $memberId,
      title: $title,
      model: $model,
      createdAt: $now,
      updatedAt: $now,
      lastMessageAt: null,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes an assistant thread from persisted state.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadId $id the assistant thread identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the owning member identifier
   * @param ?string $title the thread's display title, if any
   * @param ?string $model the model identifier used for this thread, if any
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?DateTimeImmutable $lastMessageAt the last message timestamp, if any
   *
   * @return self the reconstituted assistant thread
   */
  public static function reconstitute(
    AssistantThreadId $id,
    string $organizationId,
    string $memberId,
    ?string $title,
    ?string $model,
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
    ?DateTimeImmutable $lastMessageAt,
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      memberId: $memberId,
      title: $title,
      model: $model,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      lastMessageAt: $lastMessageAt,
    );
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   *
   * @return AssistantThreadId the assistant thread identifier
   */
  public function id(): AssistantThreadId
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
   * Method memberId.
   *
   * @since 1.0.0
   *
   * @return string the owning member identifier
   */
  public function memberId(): string
  {
    return $this->memberId;
  }

  /**
   * Method title.
   *
   * @since 1.0.0
   *
   * @return ?string the thread's display title, if any
   */
  public function title(): ?string
  {
    return $this->title;
  }

  /**
   * Method model.
   *
   * @since 1.0.0
   *
   * @return ?string the model identifier used for this thread, if known
   */
  public function model(): ?string
  {
    return $this->model;
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
   * Method lastMessageAt.
   *
   * @since 1.0.0
   *
   * @return ?DateTimeImmutable the last message timestamp, if any
   */
  public function lastMessageAt(): ?DateTimeImmutable
  {
    return $this->lastMessageAt;
  }

  /**
   * Method belongsTo.
   *
   * Reports whether this thread belongs to the given organization AND
   * member. A member may only ever read/use their OWN assistant threads —
   * every use case must call this before returning a thread found by id.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier to match
   * @param string $memberId the member identifier to match
   *
   * @return bool true when both match
   */
  public function belongsTo(string $organizationId, string $memberId): bool
  {
    return $this->organizationId === $organizationId && $this->memberId === $memberId;
  }

  /**
   * Method recordActivity.
   *
   * Bumps {@see self::lastMessageAt()} and {@see self::updatedAt()} whenever
   * a message is appended to this thread.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current time
   */
  public function recordActivity(DateTimeImmutable $now): void
  {
    $this->lastMessageAt = $now;
    $this->updatedAt = $now;
  }
  // #endregion
}
