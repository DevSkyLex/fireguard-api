<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\NotificationPreference;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO NotificationPreferenceOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationPreferenceOutput
{
  // #region Properties
  /**
   * Property category.
   *
   * The notification category this customization applies to (the
   * `{category}` half of a `{category}.{action}` notification type).
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::PREFERENCES])]
  #[ApiProperty(readable: true, writable: false)]
  public string $category = '';

  /**
   * Property emailEnabled.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::PREFERENCES])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $emailEnabled = true;

  /**
   * Property mercureEnabled.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::PREFERENCES])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $mercureEnabled = true;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[Groups([NotificationSerializationGroup::PREFERENCES])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $updatedAt = null;
  // #endregion
}
