<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use DateTimeImmutable;

/** Persisted edit, tombstone and creation timestamps. */
final readonly class RestoredMessageLifecycle
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted edit, deletion and creation timestamps for a message.
   *
   * @access public
   *
   * @param ?DateTimeImmutable $editedAt timestamp of the most recent edit, when present
   * @param ?DateTimeImmutable $deletedAt timestamp of deletion, when the message is tombstoned
   * @param ?string $deletedByMemberId optional member who deleted the message
   * @param DateTimeImmutable $createdAt original creation timestamp
   * @param DateTimeImmutable $updatedAt most recent update timestamp
   *
   * @return void
   */
  public function __construct(
    public ?DateTimeImmutable $editedAt,
    public ?DateTimeImmutable $deletedAt,
    public ?string $deletedByMemberId,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
