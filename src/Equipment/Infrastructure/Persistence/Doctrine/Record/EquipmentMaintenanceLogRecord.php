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
#[ORM\UniqueConstraint(name: 'uniq_maintenance_log_dedup', columns: ['dedup_key'])]
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

  /**
   * Property source.
   *
   * What produced this entry: `status_transition` (default, existing
   * maintenance windows) or `intervention` (point-in-time service record
   * synthesized from a published intervention).
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'string', length: 32, options: ['default' => 'status_transition'])]
  public string $source = 'status_transition';

  /**
   * Property interventionId.
   *
   * The source intervention identifier, set for `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_id', type: 'string', length: 36, nullable: true)]
  public ?string $interventionId = null;

  /**
   * Property interventionNumber.
   *
   * The source intervention's human-readable per-organization number, set
   * for `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'intervention_number', type: 'integer', nullable: true)]
  public ?int $interventionNumber = null;

  /**
   * Property workItemAction.
   *
   * The linked work item action, or a derived label when no work item is
   * linked, set for `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'work_item_action', type: 'string', length: 64, nullable: true)]
  public ?string $workItemAction = null;

  /**
   * Property actorId.
   *
   * The acting user identifier, when known, set for `intervention` entries only.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'actor_id', type: 'string', length: 36, nullable: true)]
  public ?string $actorId = null;

  /**
   * Property summary.
   *
   * A free-form summary of the service performed, set for `intervention`
   * entries only.
   *
   * @since 1.0.0
   */
  #[ORM\Column(type: 'text', nullable: true)]
  public ?string $summary = null;

  /**
   * Property dedupKey.
   *
   * Idempotency key for the raw-DBAL intervention-service insert
   * ({@see \Equipment\Infrastructure\Persistence\Doctrine\Repository\MaintenanceLogRepository::appendInterventionServiceEntry()}).
   * Null for `status_transition` entries, which are never subject to
   * at-least-once redelivery.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'dedup_key', type: 'string', length: 64, nullable: true)]
  public ?string $dedupKey = null;
  // #endregion
}
