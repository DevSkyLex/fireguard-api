<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingMessageLinkRecord.
 *
 * A URL extracted from a message body on create/edit (B2). No domain
 * aggregate: a pure satellite row, mirroring `messaging_reactions`/
 * `messaging_saved_messages` (see `MODULE.md`'s "Domain Model") —
 * `MessagingLinkRepository` writes/reads it directly via raw DBAL/DQL,
 * never through a load-modify-save aggregate cycle.
 *
 * `conversation_id` is denormalized (plain indexed column, no association),
 * mirroring `MessagingAttachmentRecord::$conversationId`, so the
 * conversation Links tab lists links without joining through
 * `messaging_messages`.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_message_link')]
#[ORM\Index(name: 'idx_messaging_message_link_conversation', columns: ['conversation_id', 'created_at'])]
#[ORM\Index(name: 'idx_messaging_message_link_message', columns: ['message_id'])]
class MessagingMessageLinkRecord
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
   * Property url.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'url', type: 'string', length: 2048)]
  public string $url;

  /**
   * Property label.
   *
   * Never populated by extraction today — reserved for a future
   * link-preview feature (e.g. an `<title>` fetch).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: true)]
  public ?string $label = null;

  /**
   * Property createdAt.
   *
   * The extraction date — the owning message's creation/edit date.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
  // #endregion
}
