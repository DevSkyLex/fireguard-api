<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingSavedMessageRecord.
 *
 * A member's personal bookmark on a message, composite-keyed (`member_id`,
 * `message_id`) so saving twice is idempotent. Backs the "Saved items" list,
 * which is per-member and never visible to other members — unlike a pin, which
 * is a property of the conversation.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_saved_messages')]
#[ORM\Index(name: 'idx_messaging_saved_org_member_saved', columns: ['organization_id', 'member_id', 'saved_at'])]
class MessagingSavedMessageRecord
{
  // #region Properties
  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'member_id', type: 'string', length: 36)]
  public string $memberId;

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
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property savedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'saved_at', type: 'datetime_immutable')]
  public DateTimeImmutable $savedAt;
  // #endregion
}
