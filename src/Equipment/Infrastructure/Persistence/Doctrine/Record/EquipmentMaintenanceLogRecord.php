<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record EquipmentMaintenanceLogRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipment_maintenance_logs')]
#[ORM\Index(name: 'idx_maintenance_log_equipment', columns: ['equipment_id'])]
#[ORM\Index(name: 'idx_maintenance_log_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_maintenance_log_started_at', columns: ['started_at'])]
class EquipmentMaintenanceLogRecord
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
   * Property equipment.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: EquipmentRecord::class)]
  #[ORM\JoinColumn(name: 'equipment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?EquipmentRecord $equipment = null;

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property startedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
  public DateTimeImmutable $startedAt;

  /**
   * Property completedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $completedAt = null;
  // #endregion
}
