<?php

declare(strict_types=1);

namespace Notification\Domain\Model\Notification;

use Shared\Domain\ValueObject\Email;

/** Optional user, email and organization target of a new notification. */
final readonly class NotificationTarget
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures optional recipients and organization scope for a notification.
   *
   * @access public
   *
   * @param ?string $recipientUserId optional user identifier receiving the notification
   * @param ?Email $recipientEmail optional email recipient
   * @param ?string $organizationId optional organization associated with the notification
   *
   * @return void
   */
  public function __construct(
    public ?string $recipientUserId = null,
    public ?Email $recipientEmail = null,
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
