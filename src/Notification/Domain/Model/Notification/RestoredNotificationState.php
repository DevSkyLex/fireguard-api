<?php

declare(strict_types=1);

namespace Notification\Domain\Model\Notification;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Email;

/** Persisted delivery, recipient and read state restored without changing the stored record. */
final readonly class RestoredNotificationState
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted recipient, delivery and read state during restoration.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt original creation timestamp
   * @param DateTimeImmutable $updatedAt most recent update timestamp
   * @param ?string $recipientUserId optional recipient user identifier
   * @param ?Email $recipientEmail optional email recipient
   * @param bool $isRead whether the notification has been read
   * @param ?DateTimeImmutable $readAt timestamp when the notification was read, if applicable
   * @param ?string $organizationId optional organization associated with the notification
   *
   * @return void
   */
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
  // #endregion
}
