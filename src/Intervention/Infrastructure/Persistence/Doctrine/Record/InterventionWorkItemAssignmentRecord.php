<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record InterventionWorkItemAssignmentRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'intervention_work_item_assignments')]
#[ORM\Index(name: 'idx_work_item_assignment_member', columns: ['work_item_id', 'member_id'])]
class InterventionWorkItemAssignmentRecord
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
  #[ORM\JoinColumn(name: 'work_item_id', nullable: false, onDelete: 'CASCADE')]
  public ?InterventionWorkItemRecord $workItem = null;

  /**
   * Property memberId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36)]
  public string $memberId;

  /**
   * Property assignedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable')]
  public DateTimeImmutable $assignedAt;

  /**
   * Property unassignedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $unassignedAt = null;

  /**
   * Property actorId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 36, nullable: true)]
  public ?string $actorId = null;
}
