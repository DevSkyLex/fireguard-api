<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionTimeEntryRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_time_entries')]
#[ORM\Index(name: 'idx_time_entry_member_date', columns: ['organization_id', 'member_id', 'worked_on'])]
class InterventionTimeEntryRecord
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property workItem.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: InterventionWorkItemRecord::class)]
  #[ORM\JoinColumn(name: 'work_item_id', nullable: false, onDelete: 'RESTRICT')]
  public ?InterventionWorkItemRecord $workItem = null;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $memberId;

  /**
   * Property workedOn.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 10)]
  public string $workedOn;

  /**
   * Property minutes.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $minutes;

  /**
   * Property note.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $note = null;

  /**
   * Property cancelled.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'boolean')]
  public bool $cancelled = false;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;

  /**
   * Property createdBy.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $createdBy;

  /**
   * Property updatedBy.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $updatedBy;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
}
