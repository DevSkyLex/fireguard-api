<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingMessageRecord.
 *
 * The body is RETAINED even when the message is tombstoned
 * (`deleted_at`/`deleted_by_member_id` set) — a compliance product
 * decision; redaction happens at the Presentation layer
 * (`MessageOutputFactory`), never here.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_messages')]
#[ORM\Index(name: 'idx_messaging_message_conversation_created', columns: ['conversation_id', 'created_at'])]
#[ORM\Index(name: 'idx_messaging_message_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_messaging_message_parent', columns: ['parent_message_id'])]
class MessagingMessageRecord
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
   * Property conversation.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: MessagingConversationRecord::class)]
  #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MessagingConversationRecord $conversation = null;

  /**
   * Property organizationId.
   *
   * Denormalized cross-scoping reference (avoids a join with the
   * conversation on every organization-scoped listing).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property authorMemberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'author_member_id', type: 'string', length: 36)]
  public string $authorMemberId;

  /**
   * Property body.
   *
   * Sanitized, trimmed body with `@{memberUuid}` mention tokens surviving
   * sanitization. Retained even after tombstone deletion.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'body', type: 'text')]
  public string $body;

  /**
   * Property mentions.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  #[ORM\Column(name: 'mentions', type: 'json')]
  public array $mentions = [];

  /**
   * Property editedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'edited_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $editedAt = null;

  /**
   * Property deletedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $deletedAt = null;

  /**
   * Property deletedByMemberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'deleted_by_member_id', type: 'string', length: 36, nullable: true)]
  public ?string $deletedByMemberId = null;

  /**
   * Property pinnedAt.
   *
   * Non-null when the message is pinned in its conversation. A pin is a
   * property of the CONVERSATION (visible to everyone who can read it), unlike
   * a saved message, which is private to one member.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'pinned_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $pinnedAt = null;

  /**
   * Property pinnedByMemberId.
   *
   * @since 1.1.0
   */
  #[ORM\Column(name: 'pinned_by_member_id', type: 'string', length: 36, nullable: true)]
  public ?string $pinnedByMemberId = null;

  /**
   * Property parentMessage.
   *
   * Self-referencing parent, non-null when this message is a threaded reply
   * (scaffolded by L2.0, activated by L2.5). A root message (not a reply)
   * has `parentMessage === null`; the root listing (`ListMessagesHandler`)
   * excludes replies by filtering `parent_message_id IS NULL`. `ON DELETE
   * CASCADE`: deleting the parent message deletes its whole reply subtree.
   *
   * @since 1.2.0
   */
  #[ORM\ManyToOne(targetEntity: self::class)]
  #[ORM\JoinColumn(name: 'parent_message_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
  public ?MessagingMessageRecord $parentMessage = null;

  /**
   * Property replyCount.
   *
   * The number of threaded replies to this message. Scaffolded inert by
   * L2.0 (defaults to `0` for every existing/root message) and MAINTAINED
   * by L2.5 — that lot is responsible for incrementing/decrementing it
   * (an atomic `UPDATE`, mirroring `messaging_conversations.messages_count`
   * — never a load-modify-save cycle) whenever a reply is posted or
   * removed under this message.
   *
   * @since 1.2.0
   */
  #[ORM\Column(name: 'reply_count', type: 'integer')]
  public int $replyCount = 0;

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
