<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record MessagingConversationRecord.
 *
 * `subject_id` is nullable and `visibility` already carries the `subject`
 * default (with `participants` reserved) so v2 channel conversations
 * (`subject_id IS NULL`) are a purely additive schema change. `name`/
 * `team_id`/`created_by_member_id` are channel-only (v1 subject-thread
 * rows leave them null).
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_conversations')]
#[ORM\UniqueConstraint(name: 'uniq_messaging_conversation_org_subject', columns: ['organization_id', 'subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_messaging_conversation_org_archived_last_message', columns: ['organization_id', 'is_archived', 'last_message_at'])]
#[ORM\Index(name: 'idx_messaging_conversation_parent', columns: ['parent_conversation_id'])]
class MessagingConversationRecord
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
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property subjectType.
   *
   * One of `facility`, `equipment`, `intervention`, `non_conformity`.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'subject_type', type: 'string', length: 32)]
  public string $subjectType;

  /**
   * Property subjectId.
   *
   * Cross-module reference, validated through the `messaging.subject_resolver`
   * tagged-iterator seam. Nullable: v1 always sets it, v2 channels leave it
   * null.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'subject_id', type: 'string', length: 36, nullable: true)]
  public ?string $subjectId = null;

  /**
   * Property visibility.
   *
   * `subject` (v1) or `participants` (reserved for v2).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'visibility', type: 'string', length: 16)]
  public string $visibility = 'subject';

  /**
   * Property lastMessageAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_message_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastMessageAt = null;

  /**
   * Property messagesCount.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'messages_count', type: 'integer')]
  public int $messagesCount = 0;

  /**
   * Property isArchived.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'is_archived', type: 'boolean')]
  public bool $isArchived = false;

  /**
   * Property name.
   *
   * The channel display name. Null for a v1 subject-thread conversation.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'name', type: 'string', length: 80, nullable: true)]
  public ?string $name = null;

  /**
   * Property teamId.
   *
   * The bound organization team identifier, if any. A plain cross-module
   * reference column (no ORM association), mirroring `subject_id`.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'team_id', type: 'string', length: 36, nullable: true)]
  public ?string $teamId = null;

  /**
   * Property createdByMemberId.
   *
   * The creating member's identifier. Null for a v1 subject-thread
   * conversation.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_by_member_id', type: 'string', length: 36, nullable: true)]
  public ?string $createdByMemberId = null;

  /**
   * Property parentConversation.
   *
   * Self-referencing parent channel, non-null when this channel is nested
   * under another (scaffolded by L2.0, activated by L2.6). `ON DELETE SET
   * NULL` — deliberately NOT cascade: deleting a parent channel must not
   * silently delete its children, it only detaches them (they become
   * top-level channels).
   *
   * @since 1.1.0
   */
  #[ORM\ManyToOne(targetEntity: self::class)]
  #[ORM\JoinColumn(name: 'parent_conversation_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
  public ?MessagingConversationRecord $parentConversation = null;

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
