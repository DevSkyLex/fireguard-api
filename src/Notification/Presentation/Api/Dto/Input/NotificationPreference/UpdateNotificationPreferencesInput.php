<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Input\NotificationPreference;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateNotificationPreferencesInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateNotificationPreferencesInput
{
  // #region Properties
  /**
   * Property preferences.
   *
   * The category customizations to upsert. Categories already customized
   * but not listed here are left untouched.
   *
   * @since 1.0.0
   *
   * @var list<NotificationPreferenceItemInput>
   */
  #[Assert\Count(min: 1, minMessage: 'At least one preference entry is required.')]
  #[Assert\Valid]
  #[Groups([NotificationSerializationGroup::PREFERENCES_WRITE])]
  #[ApiProperty(description: 'Category preference entries to upsert.', required: true)]
  public array $preferences = [];
  // #endregion
}
