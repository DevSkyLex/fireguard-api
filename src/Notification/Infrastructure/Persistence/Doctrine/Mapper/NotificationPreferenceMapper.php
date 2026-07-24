<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Mapper;

use Notification\Domain\Model\NotificationPreference\NotificationPreference;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationPreferenceRecord;

/**
 * Mapper NotificationPreferenceMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationPreferenceMapper
{
  // #region Methods
  /**
   * Method toRecord.
   *
   * @since 1.0.0
   *
   * @param NotificationPreference $preference the domain preference
   *
   * @return NotificationPreferenceRecord the persistence record
   */
  public static function toRecord(NotificationPreference $preference): NotificationPreferenceRecord
  {
    $record = new NotificationPreferenceRecord();
    $record->userId = $preference->userId();
    $record->category = $preference->category();
    $record->emailEnabled = $preference->isEmailEnabled();
    $record->mercureEnabled = $preference->isMercureEnabled();
    $record->updatedAt = $preference->updatedAt();

    return $record;
  }

  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param NotificationPreferenceRecord $record the persistence record
   *
   * @return NotificationPreference the domain preference
   */
  public static function toDomain(NotificationPreferenceRecord $record): NotificationPreference
  {
    return NotificationPreference::reconstitute(
      userId: $record->userId,
      category: $record->category,
      emailEnabled: $record->emailEnabled,
      mercureEnabled: $record->mercureEnabled,
      updatedAt: $record->updatedAt,
    );
  }
  // #endregion
}
