<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record AssistantThreadRecord.
 *
 * Scaffolded by lot L2.0 (wave-2 `main` schema lot) ahead of the feature lot
 * (starting L2.1) that activates the AI assistant chat surface. `organization_id`
 * is a plain column (not an ORM association) with no foreign key, mirroring
 * `Automation\Infrastructure\Persistence\Doctrine\Record\AutomationRunRecord`'s
 * precedent: Assistant never depends on the Organization ORM mapping.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'assistant_threads')]
#[ORM\Index(name: 'idx_assistant_thread_org_member_last_message', columns: ['organization_id', 'member_id', 'last_message_at'])]
class AssistantThreadRecord
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
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'member_id', type: 'string', length: 36)]
  public string $memberId;

  /**
   * Property title.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'title', type: 'string', length: 255, nullable: true)]
  public ?string $title = null;

  /**
   * Property model.
   *
   * The model identifier used for this thread (e.g. a provider/model slug).
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'model', type: 'string', length: 120, nullable: true)]
  public ?string $model = null;

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

  /**
   * Property lastMessageAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'last_message_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $lastMessageAt = null;
  // #endregion
}
