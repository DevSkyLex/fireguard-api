<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\GetUserNotification;

use Notification\Application\Exception\NotificationNotFoundException;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetUserNotificationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserNotificationHandler implements QueryHandler
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
   * Returns one notification for a given user.
   *
   * @since 1.0.0
   *
   * @param GetUserNotificationQuery $query the query payload
   *
   * @return GetUserNotificationResult the use case result
   */
  public function __invoke(GetUserNotificationQuery $query): GetUserNotificationResult
  {
    $notification = $this->notificationRepository->findByIdForUser(
      id: NotificationId::fromString($query->notificationId),
      userId: $query->userId,
    );

    if (null === $notification) {
      throw NotificationNotFoundException::withId($query->notificationId);
    }

    return $this->mapNotification($notification);
  }

  /**
   * Method mapNotification.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification
   *
   * @return GetUserNotificationResult the mapped result
   */
  private function mapNotification(Notification $notification): GetUserNotificationResult
  {
    return new GetUserNotificationResult(
      id: (string) $notification->id(),
      type: $notification->type(),
      subject: $notification->subject(),
      body: $notification->body(),
      channels: $notification->channels(),
      payload: $notification->payload(),
      isRead: $notification->isRead(),
      createdAt: $notification->createdAt(),
      readAt: $notification->readAt(),
    );
  }
  // #endregion
}
