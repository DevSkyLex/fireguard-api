<?php

declare(strict_types=1);

namespace Notification\Domain\Model\Notification;

use Shared\Domain\ValueObject\Email;

/** Optional user, email and organization target of a new notification. */
final readonly class NotificationTarget
{
  public function __construct(
    public ?string $recipientUserId = null,
    public ?Email $recipientEmail = null,
    public ?string $organizationId = null,
  ) {
  }
}
