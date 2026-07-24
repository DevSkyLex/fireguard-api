<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingReadMarkerRecord.
 *
 * Per-member read marker for a conversation, composite-keyed
 * (`conversation_id`, `member_id`), used to compute unread counts.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_read_markers')]
#[ORM\Index(name: 'idx_messaging_read_marker_org_member', columns: ['organization_id', 'member_id'])]
class MessagingReadMarkerRecord
{
  // #region Properties
  /**
   * Property conversation.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: MessagingConversationRecord::class)]
  #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MessagingConversationRecord $conversation = null;

  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'member_id', type: 'string', length: 36)]
  public string $memberId;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property lastReadAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_read_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastReadAt = null;

  /**
   * Property lastReadMessageId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_read_message_id', type: 'string', length: 36, nullable: true)]
  public ?string $lastReadMessageId = null;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
