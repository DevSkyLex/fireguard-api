<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\Inbox;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO InboxOutput.
 *
 * Cursor-paginated response envelope for `GET /api/inbox`. Deliberately not
 * a standard API Platform collection (no `page`/`totalItems`): the feed is
 * a bounded merge over heterogeneous sources, so only forward cursor
 * pagination (`nextCursor`/`hasMore`) is stable.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InboxOutput
{
  // #region Properties
  /**
   * Property items.
   *
   * @var list<InboxItemOutput>
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $items = [];

  /**
   * Property nextCursor.
   *
   * The `before` value to send on the next request to fetch the following
   * page, or null when there is no further page.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $nextCursor = null;

  /**
   * Property hasMore.
   *
   * True when a further page is likely available.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $hasMore = false;
  // #endregion
}
