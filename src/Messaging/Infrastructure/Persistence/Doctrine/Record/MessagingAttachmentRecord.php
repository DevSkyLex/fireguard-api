<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingAttachmentRecord.
 *
 * Files posted alongside a message. The column set intentionally mirrors
 * `inspection_attachments` / `intervention_attachments` / `facility_attachments`
 * rather than unifying them: at five owners the duplication is still cheaper
 * than a four-module, four-migration unification whose column sets are not
 * actually identical (see `src/Messaging/MODULE.md`).
 *
 * `conversation_id` is denormalized (plain indexed column, no association) so
 * the conversation Files tab lists attachments without joining through
 * `messaging_messages` — the same denormalization
 * {@see MessagingMessageRecord} applies to `organization_id`. Deletion still
 * cascades correctly through the `message_id` foreign key.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_attachments')]
#[ORM\Index(name: 'idx_messaging_attachment_message', columns: ['message_id'])]
#[ORM\Index(name: 'idx_messaging_attachment_conversation_uploaded', columns: ['conversation_id', 'uploaded_at'])]
#[ORM\Index(name: 'idx_messaging_attachment_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_messaging_attachment_storage_path', columns: ['storage_path'])]
class MessagingAttachmentRecord
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
   * Property message.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: MessagingMessageRecord::class)]
  #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MessagingMessageRecord $message = null;

  /**
   * Property conversationId.
   *
   * Denormalized owning conversation (no association — see the class docblock).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'conversation_id', type: 'string', length: 36)]
  public string $conversationId;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property uploadedByMemberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'uploaded_by_member_id', type: 'string', length: 36)]
  public string $uploadedByMemberId;

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'file_name', type: 'string', length: 255)]
  public string $fileName;

  /**
   * Property storagePath.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'storage_path', type: 'string', length: 500)]
  public string $storagePath;

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'mime_type', type: 'string', length: 100)]
  public string $mimeType;

  /**
   * Property size.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'size', type: 'bigint')]
  public int $size;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: true)]
  public ?string $label = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'uploaded_at', type: 'datetime_immutable')]
  public DateTimeImmutable $uploadedAt;
  // #endregion
}
