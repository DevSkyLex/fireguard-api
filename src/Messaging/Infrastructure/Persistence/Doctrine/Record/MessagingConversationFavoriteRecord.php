<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingConversationFavoriteRecord.
 *
 * A member's personal star on a conversation or channel, composite-keyed
 * (`conversation_id`, `member_id`) so favoriting twice is idempotent. Purely a
 * sidebar ordering concern: a favorite grants no access, so it must never be
 * consulted when authorizing a read.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_conversation_favorites')]
#[ORM\Index(name: 'idx_messaging_favorite_org_member', columns: ['organization_id', 'member_id'])]
class MessagingConversationFavoriteRecord
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
   * Property favoritedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'favorited_at', type: 'datetime_immutable')]
  public DateTimeImmutable $favoritedAt;
  // #endregion
}
