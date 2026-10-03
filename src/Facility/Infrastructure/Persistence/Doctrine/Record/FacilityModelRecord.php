<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record FacilityModelRecord.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'facility_models')]
#[ORM\Index(name: 'idx_facility_model_building', columns: ['building_id'])]
#[ORM\Index(name: 'idx_facility_model_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_facility_model_storage_path', columns: ['storage_path'])]
#[ORM\UniqueConstraint(name: 'uniq_facility_model_active_building', columns: ['active_building_id'])]
class FacilityModelRecord
{
  // #region Properties
  /**
   * Property id.
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property building.
   */
  #[ORM\ManyToOne(targetEntity: FacilityRecord::class)]
  #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?FacilityRecord $building = null;

  /**
   * Property fileName.
   */
  #[ORM\Column(name: 'file_name', type: 'string', length: 255)]
  public string $fileName;

  /**
   * Property storagePath.
   */
  #[ORM\Column(name: 'storage_path', type: 'string', length: 500)]
  public string $storagePath;

  /**
   * Property fileSize.
   */
  #[ORM\Column(name: 'file_size', type: 'integer')]
  public int $fileSize;

  /**
   * @var list<array{index: int, name: string}>
   */
  #[ORM\Column(type: 'json', options: ['jsonb' => true])]
  public array $nodes = [];

  /**
   * Property revision.
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;

  /**
   * Property active.
   */
  #[ORM\Column(type: 'boolean')]
  public bool $active = false;

  // The nullable unique key represents the single active model without a partial-index mapping gap.
  /**
   * Property activeBuildingId.
   */
  #[ORM\Column(name: 'active_building_id', type: 'string', length: 36, nullable: true)]
  public ?string $activeBuildingId = null;

  /**
   * @var array{scale: float, rotationDegrees: float, translation: array{x: float, y: float, z: float}}
   */
  #[ORM\Column(type: 'json', options: ['jsonb' => true])]
  public array $transform;

  /**
   * @var list<array{nodeIndex: int, facilityId: string}>
   */
  #[ORM\Column(type: 'json', options: ['jsonb' => true])]
  public array $bindings = [];

  /**
   * Property createdAt.
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
