<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Query\Notification\ListUserNotifications;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Outbound\NotificationRepositoryPort;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\GetUserNotificationResult;
use Notification\Domain\Model\Notification\Notification;
use Shared\Application\Message\QueryHandler;

use function array_map;
use function max;
use function min;

/**
 * UseCase ListUserNotificationsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserNotificationsHandler implements QueryHandler
{
  private const int HIDE_READ_AFTER_DAYS = 30;

  /**
   * @var list<string>
   */
  private const array HIDDEN_READ_CATEGORIES = [
    NotificationType::CATEGORY_USER,
    NotificationType::CATEGORY_FACILITY,
    NotificationType::CATEGORY_EQUIPMENT,
  ];

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
   * Lists notifications for a user.
   *
   * @since 1.0.0
   *
   * @param ListUserNotificationsQuery $query the query payload
   *
   * @return ListUserNotificationsResult the use case result
   */
  public function __invoke(ListUserNotificationsQuery $query): ListUserNotificationsResult
  {
    $limit = min(100, max(1, $query->pagination->limit));
    $offset = max(0, $query->pagination->offset);

    $hideReadBefore = $query->onlyUnread
      ? null
      : new DateTimeImmutable()->modify('-' . self::HIDE_READ_AFTER_DAYS . ' days');
    $hiddenReadCategories = $query->onlyUnread ? [] : self::HIDDEN_READ_CATEGORIES;

    $notifications = $this->notificationRepository->findByUserId(
      userId: $query->userId,
      onlyUnread: $query->onlyUnread,
      limit: $limit,
      offset: $offset,
      type: $query->type,
      category: $query->category,
      organizationId: $query->organizationId,
      hideReadBefore: $hideReadBefore,
      hiddenReadCategories: $hiddenReadCategories,
    );

    $total = $this->notificationRepository->countByUserId(
      userId: $query->userId,
      onlyUnread: $query->onlyUnread,
      type: $query->type,
      category: $query->category,
      organizationId: $query->organizationId,
      hideReadBefore: $hideReadBefore,
      hiddenReadCategories: $hiddenReadCategories,
    );

    $results = array_map(
      fn (Notification $notification): GetUserNotificationResult => $this->mapNotification($notification),
      $notifications,
    );

    return new ListUserNotificationsResult(
      notifications: $results,
      total: $total,
      limit: $limit,
      offset: $offset,
    );
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
      organizationId: $notification->organizationId(),
    );
  }
  // #endregion
}
