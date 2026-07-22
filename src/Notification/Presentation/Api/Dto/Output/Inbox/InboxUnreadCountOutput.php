<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\Inbox;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO InboxUnreadCountOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InboxUnreadCountOutput
{
  // #region Properties
  /**
   * Property unreadCount.
   *
   * The unread item count across every registered inbox source (currently:
   * the authenticated user's own notifications), optionally scoped to one
   * organization.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::INBOX_UNREAD_COUNT])]
  #[ApiProperty(readable: true, writable: false)]
  public int $unreadCount = 0;
  // #endregion
}
