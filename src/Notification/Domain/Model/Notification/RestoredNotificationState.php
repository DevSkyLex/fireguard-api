<?php

declare(strict_types=1);

namespace Notification\Domain\Model\Notification;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Email;

/** Persisted delivery, recipient and read state restored without changing the stored record. */
final readonly class RestoredNotificationState
{
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
    public ?string $recipientUserId = null,
    public ?Email $recipientEmail = null,
    public bool $isRead = false,
    public ?DateTimeImmutable $readAt = null,
    public ?string $organizationId = null,
  ) {
  }
}
