<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record MissionChangeRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'mission_changes')]
#[ORM\Index(name: 'idx_mission_change_mission_status', columns: ['mission_id', 'status'])]
class MissionChangeRecord
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
  #[ORM\ManyToOne(targetEntity: MissionRecord::class, inversedBy: 'changes')]
  #[ORM\JoinColumn(name: 'mission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?MissionRecord $mission = null;

  /**
   * Property workItem.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: MissionWorkItemRecord::class)]
  #[ORM\JoinColumn(name: 'work_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
  public ?MissionWorkItemRecord $workItem = null;

  /**
   * Property resource.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 255)]
  public string $resource;

  /**
   * Property patch.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(type: 'json')]
  public array $patch = [];

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $status = 'proposed';

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
