<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\Notification;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO UnreadNotificationsCountOutput.
 *
 * @category DTO
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UnreadNotificationsCountOutput
{
  // #region Properties
  /**
   * Property count.
   *
   * The unread notification count for the authenticated user, optionally
   * scoped to one organization.
   *
   * @since 1.1.0
   */
  #[Groups([NotificationSerializationGroup::UNREAD_COUNT])]
  #[ApiProperty(readable: true, writable: false)]
  public int $count = 0;
  // #endregion
}
