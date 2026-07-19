<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingReactionRecord.
 *
 * One emoji reaction by one member on one message, composite-keyed
 * (`message_id`, `member_id`, `emoji`). The emoji itself is part of the primary
 * key: that is what makes "react" idempotent and "un-react" a plain delete,
 * with no read-modify-write and therefore no lost update under concurrent
 * reactions.
 *
 * `emoji` is stored as the raw grapheme rather than a shortcode so the set of
 * reactable emoji is a client concern, not a migration.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_reactions')]
#[ORM\Index(name: 'idx_messaging_reaction_message', columns: ['message_id'])]
#[ORM\Index(name: 'idx_messaging_reaction_org_member', columns: ['organization_id', 'member_id'])]
class MessagingReactionRecord
{
  // #region Properties
  /**
   * Property message.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: MessagingMessageRecord::class)]
  #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MessagingMessageRecord $message = null;

  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'member_id', type: 'string', length: 36)]
  public string $memberId;

  /**
   * Property emoji.
   *
   * The raw emoji grapheme. Length 32 leaves room for multi-codepoint
   * sequences (skin-tone and ZWJ compositions).
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'emoji', type: 'string', length: 32)]
  public string $emoji;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;
  // #endregion
}
