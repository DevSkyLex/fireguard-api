<?php

declare(strict_types=1);

namespace Notification\Presentation\Api\Dto\Output\NotificationPreference;

use ApiPlatform\Metadata\ApiProperty;
use Notification\Presentation\Api\Serialization\NotificationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO NotificationPreferencesOutput.
 *
 * Wraps the authenticated user's customized notification preferences.
 * Categories with no entry here are enabled on every channel — the absence
 * of a row is the "everything enabled" default, never backfilled.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationPreferencesOutput
{
  // #region Properties
  /**
   * Property preferences.
   *
   * @since 1.0.0
   *
   * @var list<NotificationPreferenceOutput>
   */
  #[Groups([NotificationSerializationGroup::PREFERENCES])]
  #[ApiProperty(readable: true, writable: false)]
  public array $preferences = [];
  // #endregion
}
