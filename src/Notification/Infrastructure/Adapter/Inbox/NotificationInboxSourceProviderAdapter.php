<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Inbox;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\{InboxCursor, InboxItem};
use Notification\Application\Contract\Notification\NotificationListCriteria;
use Notification\Application\Port\Outbound\{InboxSourceProviderPort, NotificationRepositoryPort};
use Notification\Domain\Model\Notification\Notification;

use function array_map;

/**
 * Adapter NotificationInboxSourceProviderAdapter.
 *
 * The one concrete `inbox.source_provider` adapter Notification ships for
 * itself: it exposes the user's own notifications through the unified
 * inbox seam, reusing
 * {@see NotificationRepositoryPort::findByUserId()} (the same repository
 * method `GET /api/notifications` already relies on) instead of
 * duplicating a query.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NotificationInboxSourceProviderAdapter implements InboxSourceProviderPort
{
  /**
   * The source key this adapter contributes as, and the `kind`/`targetType`
   * every item it emits carries.
   *
   * @since 1.0.0
   */
  public const string SOURCE_KEY = 'notification';

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
   * Method sourceKey.
   *
   * Returns the stable source identifier used by the unified inbox.
   *
   * @access public
   *
   * @return string the notification source key
   */
  public function sourceKey(): string
  {
    return self::SOURCE_KEY;
  }

  /**
   * Method fetch.
   *
   * Loads the user's notifications with the supplied organization and cursor filters.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param ?string $organizationId optional organization scope
   * @param ?DateTimeImmutable $before optional timestamp boundary
   * @param int $limit maximum number of inbox items to return
   * @param ?InboxCursor $cursor optional continuation cursor
   *
   * @return list<InboxItem> mapped notification inbox items
   */
  public function fetch(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit, ?InboxCursor $cursor = null): array
  {
    // Reuse the notification list query with the inbox's organization scope
    // and cursor. The other filters retain the collection defaults.
    $notifications = $this->notificationRepository->findByUserId(
      userId: $userId,
      criteria: new NotificationListCriteria(organizationId: $organizationId),
      limit: $limit,
      offset: 0,
      before: $before,
      cursor: $cursor,
    );

    return array_map($this->toInboxItem(...), $notifications);
  }

  /**
   * Method countUnread.
   *
   * Counts unread notifications within the optional organization scope.
   *
   * @access public
   *
   * @param string $userId the user identifier
   * @param ?string $organizationId optional organization scope
   *
   * @return int number of unread notifications
   */
  public function countUnread(string $userId, ?string $organizationId): int
  {
    return $this->notificationRepository->countUnreadByUserId(
      userId: $userId,
      organizationId: $organizationId,
    );
  }

  /**
   * Method toInboxItem.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification aggregate
   *
   * @return InboxItem the mapped inbox item
   */
  private function toInboxItem(Notification $notification): InboxItem
  {
    return new InboxItem(
      sourceKey: self::SOURCE_KEY,
      id: (string) $notification->id(),
      kind: self::SOURCE_KEY,
      sourceType: $notification->type(),
      title: $notification->subject(),
      snippet: $notification->body(),
      occurredAt: $notification->createdAt(),
      isRead: $notification->isRead(),
      organizationId: $notification->organizationId(),
      targetType: self::SOURCE_KEY,
      targetId: (string) $notification->id(),
    );
  }
  // #endregion
}
