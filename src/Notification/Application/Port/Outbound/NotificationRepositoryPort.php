<?php

declare(strict_types=1);

namespace Notification\Application\Port\Outbound;

use DateTimeImmutable;
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
   * @param int $offset zero-based row offset for pagination
   * @param string|null $type exact type filter (e.g. `organization.invitation`)
   * @param string|null $category category prefix filter (e.g. `organization`)
   * @param string|null $organizationId exact organization filter; when null, notifications across all organizations (and account-level ones) are returned
   * @param DateTimeImmutable|null $hideReadBefore hides read notifications older than this cutoff for selected categories
   * @param list<string> $hiddenReadCategories category prefixes subject to read-history masking
   * @param DateTimeImmutable|null $before cursor: when provided, restricts results to notifications created strictly before this instant (used by the unified inbox seam for stable, non-offset pagination); when null, no cursor restriction applies
   *
   * @return list<Notification> the notifications
   */
  public function findByUserId(
    string $userId,
    bool $onlyUnread = false,
    int $limit = 50,
    int $offset = 0,
    ?string $type = null,
    ?string $category = null,
    ?string $organizationId = null,
    ?DateTimeImmutable $hideReadBefore = null,
    array $hiddenReadCategories = [],
    ?DateTimeImmutable $before = null,
  ): array;

  /**
   * Method countByUserId.
   *
   * Counts notifications for a user, applying the same filters and
   * read-visibility masking as {@see self::findByUserId()}, but without
   * pagination — used to compute the total for a paginated collection.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param bool $onlyUnread whether to count only unread notifications
   * @param string|null $type exact type filter (e.g. `organization.invitation`)
   * @param string|null $category category prefix filter (e.g. `organization`)
   * @param string|null $organizationId exact organization filter; when null, notifications across all organizations (and account-level ones) are counted
   * @param DateTimeImmutable|null $hideReadBefore hides read notifications older than this cutoff for selected categories
   * @param list<string> $hiddenReadCategories category prefixes subject to read-history masking
   *
   * @return int the total matching count
   */
  public function countByUserId(
    string $userId,
    bool $onlyUnread = false,
    ?string $type = null,
    ?string $category = null,
    ?string $organizationId = null,
    ?DateTimeImmutable $hideReadBefore = null,
    array $hiddenReadCategories = [],
  ): int;

  /**
   * Method countUnreadByUserId.
   *
   * Counts unread notifications for a user, optionally scoped to one
   * organization.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string|null $organizationId exact organization filter; when null, unread notifications across all organizations (and account-level ones) are counted
   *
   * @return int the unread count
   */
  public function countUnreadByUserId(string $userId, ?string $organizationId = null): int;

  /**
   * Method markAllAsReadForUser.
   *
   * Marks every unread notification of a user as read in a single bulk
   * update, optionally scoped to one organization. Idempotent: calling it
   * again once every notification is already read affects zero rows.
   *
   * @since 1.1.0
   *
   * @param string $userId the user identifier
   * @param string|null $organizationId exact organization filter; when null, notifications across all organizations (and account-level ones) are affected
   * @param DateTimeImmutable|null $readAt the read datetime (defaults to now)
   *
   * @return int the number of notifications marked as read
   */
  public function markAllAsReadForUser(string $userId, ?string $organizationId = null, ?DateTimeImmutable $readAt = null): int;
  // #endregion
}
