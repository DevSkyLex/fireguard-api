<?php

declare(strict_types=1);

namespace User\Application\EventHandler;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Inbound\NotificationPort;
use Shared\Application\Port\Outbound\LoggerPort;
use Throwable;
use User\Domain\Event\UserEmailVerifiedEvent;

/**
 * Handler UserEmailVerifiedEventHandler.
 *
 * @category EventHandler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserEmailVerifiedEventHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UserEmailVerifiedEventHandler class.
   *
   * @since 1.0.0
   *
   * @param LoggerPort $logger the logger
   */
  public function __construct(
    private NotificationPort $notificationPort,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the event.
   *
   * @since 1.0.0
   *
   * @param UserEmailVerifiedEvent $event the event
   */
  public function __invoke(UserEmailVerifiedEvent $event): void
  {
    $verifiedAt = $event->occurredAt->format('c');

    try {
      // organizationId is intentionally omitted: email verification is an
      // account-level event with no organization in scope, not an omission.
      $this->notificationPort->send(new SendNotificationRequest(
        type: NotificationType::USER_EMAIL_VERIFIED,
        subject: 'Your email has been verified',
        body: 'Your email address has been verified successfully.',
        channels: [NotificationChannel::MERCURE],
        payload: [
          'verifiedAt' => $verifiedAt,
        ],
        recipientUserId: $event->userId,
      ));
    } catch (Throwable $exception) {
      $this->logger->warning(
        message: 'Failed to send user email verified notification.',
        context: [
          'user_id' => $event->userId,
          'error' => $exception->getMessage(),
        ],
      );
    }

    $this->logger->info(
      message: 'User email verified',
      context: [
        'user_id' => $event->userId,
        'email' => $event->email,
      ],
    );
  }
  // #endregion
}
