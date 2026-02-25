<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;

/**
 * Port NotificationRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface NotificationRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a notification.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification
   */
  public function save(Notification $notification): void;

  /**
   * Method findByIdForUser.
   *
   * Returns a notification visible by a specific user.
   *
   * @since 1.0.0
   *
   * @param NotificationId $id the notification identifier
   * @param string $userId the user identifier
   *
   * @return Notification|null the notification when found
   */
  public function findByIdForUser(NotificationId $id, string $userId): ?Notification;

  /**
   * Method findByUserId.
   *
   * Lists notifications for a user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param bool $onlyUnread whether to return only unread notifications
   * @param int $limit max number of results
   * @param string|null $type exact type filter (e.g. `organization.invitation`)
   * @param string|null $category category prefix filter (e.g. `organization`)
   *
   * @return list<Notification> the notifications
   */
  public function findByUserId(
    string $userId,
    bool $onlyUnread = false,
    int $limit = 50,
    ?string $type = null,
    ?string $category = null,
  ): array;
  // #endregion
}
