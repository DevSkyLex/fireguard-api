<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\NotificationType;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO NotificationTypeOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationTypeOutput
{
  // #region Properties
  /**
   * Property type.
   *
   * The full dot-notation type identifier, e.g. `organization.invitation`.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::NOTIFICATION_TYPE_READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $type = '';

  /**
   * Property category.
   *
   * The category prefix extracted from the type, e.g. `organization`.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::NOTIFICATION_TYPE_READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $category = '';
  // #endregion
}
