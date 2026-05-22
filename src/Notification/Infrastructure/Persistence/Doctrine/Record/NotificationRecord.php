<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record NotificationRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_recipient_user_id', columns: ['recipient_user_id'])]
#[ORM\Index(name: 'idx_notifications_recipient_email', columns: ['recipient_email'])]
#[ORM\Index(name: 'idx_notifications_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_notifications_user_read', columns: ['recipient_user_id', 'is_read'])]
class NotificationRecord
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property recipientUserId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'recipient_user_id', type: 'string', length: 36, nullable: true)]
  public ?string $recipientUserId = null;

  /**
   * Property recipientEmail.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'recipient_email', type: 'string', length: 320, nullable: true)]
  public ?string $recipientEmail = null;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 120)]
  public string $type;

  /**
   * Property subject.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255)]
  public string $subject;

  /**
   * Property body.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text')]
  public string $body;

  /**
   * Property payload.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(type: 'json')]
  public array $payload = [];

  /**
   * Property channels.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(type: 'json')]
  public array $channels = [];

  /**
   * Property isRead.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_read', type: 'boolean')]
  public bool $isRead = false;

  /**
   * Property readAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'read_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $readAt = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
