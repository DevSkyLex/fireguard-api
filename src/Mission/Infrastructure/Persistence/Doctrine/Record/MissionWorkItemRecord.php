<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MissionWorkItemRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'mission_work_items')]
#[ORM\Index(name: 'idx_mission_work_item_mission_status', columns: ['mission_id', 'status'])]
#[ORM\Index(name: 'idx_mission_work_item_assignee', columns: ['assignee_id'])]
class MissionWorkItemRecord
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
   * Property mission.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: MissionRecord::class, inversedBy: 'workItems')]
  #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MissionRecord $mission = null;

  /**
   * Property action.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 24)]
  public string $action;

  /**
   * Property target.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  public ?string $target = null;

  /**
   * Property resultResource.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'result_resource', type: 'string', length: 255, nullable: true)]
  public ?string $resultResource = null;

  /**
   * Property assigneeId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'assignee_id', type: 'string', length: 36, nullable: true)]
  public ?string $assigneeId = null;

  /**
   * Property source.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $source = 'planned';

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $status = 'planned';

  /**
   * Property required.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'boolean')]
  public bool $required = true;

  /**
   * Property skipReason.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'skip_reason', type: 'text', nullable: true)]
  public ?string $skipReason = null;

  /**
   * Property revision.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;

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
}
