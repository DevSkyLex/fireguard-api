<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Message;

use DateTimeImmutable;

/** Persisted edit, tombstone and creation timestamps. */
final readonly class RestoredMessageLifecycle
{
  public function __construct(
    public ?DateTimeImmutable $editedAt,
    public ?DateTimeImmutable $deletedAt,
    public ?string $deletedByMemberId,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
