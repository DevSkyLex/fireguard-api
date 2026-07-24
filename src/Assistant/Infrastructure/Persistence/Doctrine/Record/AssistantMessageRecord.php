<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record AssistantMessageRecord.
 *
 * Scaffolded by lot L2.0 ahead of the feature lot (starting L2.1) that
 * activates the AI assistant chat surface. `thread_id` carries a real
 * foreign key to `assistant_threads` (ON DELETE CASCADE) since both tables
 * live in the same module; `organization_id` is a plain denormalized column
 * with no foreign key, mirroring `AssistantThreadRecord`.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'assistant_messages')]
#[ORM\Index(name: 'idx_assistant_message_thread_created', columns: ['thread_id', 'created_at'])]
class AssistantMessageRecord
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
   * Property thread.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: AssistantThreadRecord::class)]
  #[ORM\JoinColumn(name: 'thread_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?AssistantThreadRecord $thread = null;

  /**
   * Property organizationId.
   *
   * Denormalized cross-scoping reference (avoids a join with the thread on
   * every organization-scoped listing).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property role.
   *
   * One of `user`|`assistant`.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'role', type: 'string', length: 16)]
  public string $role;

  /**
   * Property body.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'body', type: 'text')]
  public string $body;

  /**
   * Property status.
   *
   * One of `pending`|`streaming`|`complete`|`failed`. **Load-bearing**: this
   * is what lets a Messenger retry on a partially-streamed reply REPLACE
   * that same row in place (transitioning `pending`/`streaming` ->
   * `complete`|`failed`) instead of appending a second, duplicate assistant
   * message for the same turn.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 16)]
  public string $status;

  /**
   * Property errorCode.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'error_code', type: 'string', length: 64, nullable: true)]
  public ?string $errorCode = null;

  /**
   * Property tokenCount.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'token_count', type: 'integer', nullable: true)]
  public ?int $tokenCount = null;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $completedAt = null;
  // #endregion
}
