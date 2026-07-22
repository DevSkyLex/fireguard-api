<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Inbox;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;
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
 *
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
  public function sourceKey(): string
  {
    return self::SOURCE_KEY;
  }

  public function fetch(string $userId, ?string $organizationId, ?DateTimeImmutable $before, int $limit): array
  {
    // Every parameter is passed explicitly (even where it is just the
    // default) so the intent is unambiguous: this adapter reuses the same
    // filtered query the paginated notification list relies on, adding
    // only the cursor.
    $notifications = $this->notificationRepository->findByUserId(
      userId: $userId,
      onlyUnread: false,
      limit: $limit,
      offset: 0,
      type: null,
      category: null,
      organizationId: $organizationId,
      hideReadBefore: null,
      hiddenReadCategories: [],
      before: $before,
    );

    return array_map($this->toInboxItem(...), $notifications);
  }

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
