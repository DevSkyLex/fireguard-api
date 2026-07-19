<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Thread;

use Assistant\Domain\Model\Thread\AssistantThread;
use DateTimeImmutable;

/**
 * Contract AssistantThreadView.
 *
 * Read model for a persisted assistant thread, shared across the
 * start/list/get use cases (mirrors
 * {@see \Messaging\Application\Contract\Channel\ChannelView}).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantThreadView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the assistant thread identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the owning member identifier
   * @param ?string $title the thread's display title, if any
   * @param ?string $model the model identifier used for this thread, if known
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $updatedAt the last update timestamp
   * @param ?DateTimeImmutable $lastMessageAt the last message timestamp, if any
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $memberId,
    public ?string $title,
    public ?string $model,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?DateTimeImmutable $lastMessageAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantThread $thread the assistant thread aggregate
   *
   * @return self the view built from the aggregate
   */
  public static function fromDomain(AssistantThread $thread): self
  {
    return new self(
      id: (string) $thread->id(),
      organizationId: $thread->organizationId(),
      memberId: $thread->memberId(),
      title: $thread->title(),
      model: $thread->model(),
      createdAt: $thread->createdAt(),
      updatedAt: $thread->updatedAt(),
      lastMessageAt: $thread->lastMessageAt(),
    );
  }
  // #endregion
}
