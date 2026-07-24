<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\MarkNotificationAsRead;

use Notification\Application\Exception\NotificationNotFoundException;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Shared\Application\Message\CommandHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase MarkNotificationAsReadHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MarkNotificationAsReadHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationRepositoryPort $notificationRepository the notification repository port
   */
  public function __construct(
    private NotificationRepositoryPort $notificationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Marks one user notification as read.
   *
   * @since 1.0.0
   *
   * @param MarkNotificationAsReadCommand $command the command payload
   *
   * @throws NotificationNotFoundException when notification is not accessible for user
   *
   * @return MarkNotificationAsReadResult the use case result
   */
  public function __invoke(MarkNotificationAsReadCommand $command): MarkNotificationAsReadResult
  {
    try {
      $notificationId = NotificationId::fromString($command->notificationId);
    } catch (InvalidValueException) {
      throw NotificationNotFoundException::withId($command->notificationId);
    }

    $notification = $this->notificationRepository->findByIdForUser(
      id: $notificationId,
      userId: $command->userId,
    );

    if (null === $notification) {
      throw NotificationNotFoundException::withId($command->notificationId);
    }

    if (!$notification->isRead()) {
      $notification->markAsRead();
      $this->notificationRepository->save($notification);
    }

    return $this->mapResult($notification);
  }

  /**
   * Method mapResult.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification aggregate
   *
   * @return MarkNotificationAsReadResult the mapped result
   */
  private function mapResult(Notification $notification): MarkNotificationAsReadResult
  {
    return new MarkNotificationAsReadResult(
      id: (string) $notification->id(),
      type: $notification->type(),
      subject: $notification->subject(),
      body: $notification->body(),
      channels: $notification->channels(),
      payload: $notification->payload(),
      isRead: $notification->isRead(),
      createdAt: $notification->createdAt(),
      readAt: $notification->readAt(),
      organizationId: $notification->organizationId(),
    );
  }
  // #endregion
}
