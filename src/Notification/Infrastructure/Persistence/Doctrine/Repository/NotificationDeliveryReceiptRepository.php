<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Notification\Application\Port\Outbound\NotificationDeliveryReceiptPort;
use Notification\Domain\Model\Notification\Notification;
use Notification\Infrastructure\Persistence\Doctrine\Mapper\NotificationMapper;
use Notification\Infrastructure\Persistence\Doctrine\Record\NotificationRecord;

use function hash;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Commits local inbox and channel receipts independently of an outer event consumer retry.
 *
 * @category Repository
 */
final readonly class NotificationDeliveryReceiptRepository implements NotificationDeliveryReceiptPort
{
  private Connection $receipts;

  /**
   * The dedicated connection targets the same main database, never auth.
   */
  public function __construct(Connection $connection)
  {
    $this->receipts = DriverManager::getConnection($connection->getParams());
  }

  /**
   * @template T
   *
   * @param callable(): T $work delivery operation
   *
   * @return T delivery result
   */
  public function synchronized(string $key, callable $work): mixed
  {
    $lock = ['key' => 'notification.delivery.' . hash('sha256', $key)];
    $this->receipts->executeStatement('SELECT pg_advisory_lock(hashtextextended(:key, 0))', $lock);

    try {
      return $work();
    } finally {
      $this->receipts->executeStatement('SELECT pg_advisory_unlock(hashtextextended(:key, 0))', $lock);
    }
  }

  /**
   * Reconstitutes the acknowledged inbox row without ORM identity-map retention.
   */
  public function find(string $key): ?Notification
  {
    /** @var array{id: string, type: string, subject: string, body: string, recipient_user_id: ?string, recipient_email: ?string, organization_id: ?string, payload: string, channels: string, is_read: bool|string|int, read_at: ?string, created_at: string, updated_at: string}|false $row */
    $row = $this->receipts->fetchAssociative('SELECT n.* FROM notifications n INNER JOIN notification_delivery_receipts r ON n.id = r.notification_id WHERE r.id = :id', ['id' => hash('sha256', $key)]);
    if (false === $row) {
      return null;
    }
    $record = new NotificationRecord();
    $record->id = (string) $row['id'];
    $record->type = (string) $row['type'];
    $record->subject = (string) $row['subject'];
    $record->body = (string) $row['body'];
    $record->recipientUserId = $row['recipient_user_id'];
    $record->recipientEmail = $row['recipient_email'];
    $record->organizationId = $row['organization_id'];
    /** @var array<string, mixed> $payload */
    $payload = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
    /** @var list<string> $channels */
    $channels = json_decode($row['channels'], true, flags: JSON_THROW_ON_ERROR);
    $record->payload = $payload;
    $record->channels = $channels;
    $record->isRead = true === $row['is_read'] || 't' === $row['is_read'] || 1 === $row['is_read'];
    $record->readAt = null === $row['read_at'] ? null : new DateTimeImmutable((string) $row['read_at']);
    $record->createdAt = new DateTimeImmutable((string) $row['created_at']);
    $record->updatedAt = new DateTimeImmutable((string) $row['updated_at']);

    return NotificationMapper::toDomain($record);
  }

  /**
   * Stores both local consequences in one main transaction.
   */
  public function save(string $key, Notification $notification): void
  {
    $this->receipts->transactional(function () use ($key, $notification): void {
      $this->receipts->executeStatement(
        'INSERT INTO notifications (id, recipient_user_id, recipient_email, organization_id, type, subject, body, payload, channels, is_read, read_at, created_at, updated_at)
         VALUES (:id, :user, :email, :organization, :type, :subject, :body, :payload, :channels, FALSE, NULL, :created, :updated)',
        ['id' => (string) $notification->id(), 'user' => $notification->recipientUserId(), 'email' => null === $notification->recipientEmail() ? null : (string) $notification->recipientEmail(),
          'organization' => $notification->organizationId(), 'type' => $notification->type(), 'subject' => $notification->subject(), 'body' => $notification->body(),
          'payload' => json_encode($notification->payload(), JSON_THROW_ON_ERROR), 'channels' => json_encode($notification->channels(), JSON_THROW_ON_ERROR),
          'created' => $notification->createdAt()->format('Y-m-d H:i:s'), 'updated' => $notification->updatedAt()->format('Y-m-d H:i:s')],
      );
      $this->receipts->executeStatement(
        'INSERT INTO notification_delivery_receipts (id, notification_id, channel_status) VALUES (:id, :notification, :status)',
        ['id' => hash('sha256', $key), 'notification' => (string) $notification->id(), 'status' => '{}'],
      );
    });
  }

  /**
   * @return array<string, string> terminal channel outcomes
   */
  public function statuses(string $key): array
  {
    /** @var string|false $value */
    $value = $this->receipts->fetchOne('SELECT channel_status FROM notification_delivery_receipts WHERE id = :id', ['id' => hash('sha256', $key)]);

    /** @var array<string, string> $statuses */
    $statuses = false === $value ? [] : json_decode($value, true, flags: JSON_THROW_ON_ERROR);

    return $statuses;
  }

  /**
   * Keeps each acknowledgement even when a later recipient or channel fails.
   */
  public function record(string $key, string $channel, string $status): void
  {
    $this->receipts->executeStatement(
      'UPDATE notification_delivery_receipts SET channel_status = channel_status || CAST(:status AS JSONB) WHERE id = :id',
      ['id' => hash('sha256', $key), 'status' => json_encode([$channel => $status], JSON_THROW_ON_ERROR)],
    );
  }
}
