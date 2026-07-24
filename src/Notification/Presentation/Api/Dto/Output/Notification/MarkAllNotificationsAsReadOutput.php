<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\Notification;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO MarkAllNotificationsAsReadOutput.
 *
 * @category DTO
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MarkAllNotificationsAsReadOutput
{
  // #region Properties
  /**
   * Property count.
   *
   * The number of notifications marked as read by this call. Zero when
   * every notification of the authenticated user (in scope) was already
   * read — the operation is idempotent.
   *
   * @since 1.1.0
   */
  #[Groups([NotificationSerializationGroup::MARK_ALL_AS_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $count = 0;
  // #endregion
}
