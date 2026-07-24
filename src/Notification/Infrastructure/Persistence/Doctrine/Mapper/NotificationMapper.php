<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Mapper;

use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationRecord;
use Shared\Domain\ValueObject\Email;

/**
 * Mapper NotificationMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NotificationMapper
{
  // #region Methods
  /**
   * Method toRecord.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification aggregate
   *
   * @return NotificationRecord the persistence record
   */
  public static function toRecord(Notification $notification): NotificationRecord
  {
    $record = new NotificationRecord();
    $record->id = (string) $notification->id();
    $record->recipientUserId = $notification->recipientUserId();
    $record->recipientEmail = null !== $notification->recipientEmail() ? (string) $notification->recipientEmail() : null;
    $record->organizationId = $notification->organizationId();
    $record->type = $notification->type();
    $record->subject = $notification->subject();
    $record->body = $notification->body();
    $record->payload = $notification->payload();
    $record->channels = $notification->channels();
    $record->isRead = $notification->isRead();
    $record->readAt = $notification->readAt();
    $record->createdAt = $notification->createdAt();
    $record->updatedAt = $notification->updatedAt();

    return $record;
  }

  /**
   * Method toDomain.
   *
   * @since 1.0.0
   *
   * @param NotificationRecord $record the persistence record
   *
   * @return Notification the domain aggregate
   */
  public static function toDomain(NotificationRecord $record): Notification
  {
    $recipientEmail = null;
    if (null !== $record->recipientEmail) {
      $recipientEmail = new Email($record->recipientEmail);
    }

    return Notification::reconstitute(
      id: NotificationId::fromString($record->id),
      type: $record->type,
      subject: $record->subject,
      body: $record->body,
      channels: $record->channels,
      payload: $record->payload,
      createdAt: $record->createdAt,
      updatedAt: $record->updatedAt,
      recipientUserId: $record->recipientUserId,
      recipientEmail: $recipientEmail,
      isRead: $record->isRead,
      readAt: $record->readAt,
      organizationId: $record->organizationId,
    );
  }
  // #endregion
}
