<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Input\NotificationPreference;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO NotificationPreferenceItemInput.
 *
 * One category customization to upsert. Both channel flags default to
 * `true`: an upsert always writes a complete row, matching the persisted
 * columns (there is no partial-merge semantics at the field level — only at
 * the row level, since categories not sent are left untouched).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationPreferenceItemInput
{
  // #region Properties
  /**
   * Property category.
   *
   * The notification category to customize (the `{category}` half of a
   * `{category}.{action}` notification type). Unknown categories are
   * accepted on purpose, for forward-compatibility with types added
   * without a server-side deployment.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  #[Assert\Length(max: 64)]
  #[Groups([NotificationSerializationGroup::PREFERENCES_WRITE])]
  #[ApiProperty(description: 'Notification category to customize.', example: 'organization', required: true)]
  public string $category = '';

  /**
   * Property emailEnabled.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([NotificationSerializationGroup::PREFERENCES_WRITE])]
  #[ApiProperty(description: 'Whether email delivery is enabled for this category.', required: false)]
  public bool $emailEnabled = true;

  /**
   * Property mercureEnabled.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([NotificationSerializationGroup::PREFERENCES_WRITE])]
  #[ApiProperty(description: 'Whether Mercure (real-time) delivery is enabled for this category.', required: false)]
  public bool $mercureEnabled = true;
  // #endregion
}
