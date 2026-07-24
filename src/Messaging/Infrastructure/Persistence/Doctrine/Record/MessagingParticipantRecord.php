<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MessagingParticipantRecord.
 *
 * The explicit membership list of a `visibility=PARTICIPANTS` channel,
 * composite-keyed (`conversation_id`, `member_id`). `source` (`manual` or
 * `team`) makes the event-driven team reconciliation deterministic — only
 * `team`-sourced rows are ever pruned by
 * {@see \Messaging\Application\Service\MessagingChannelParticipantSynchronizer}.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaging_participants')]
#[ORM\Index(name: 'idx_messaging_participant_org_member', columns: ['organization_id', 'member_id'])]
class MessagingParticipantRecord
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
   * Property role.
   *
   * Free-form membership label, if any.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'role', type: 'string', length: 32, nullable: true)]
  public ?string $role = null;

  /**
   * Property source.
   *
   * `manual` (added through the channel participants endpoint) or `team`
   * (reconciled from a bound organization team).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'source', type: 'string', length: 16)]
  public string $source = 'manual';

  /**
   * Property addedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'added_at', type: 'datetime_immutable')]
  public DateTimeImmutable $addedAt;
  // #endregion
}
