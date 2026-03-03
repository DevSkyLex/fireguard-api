<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Record EquipmentRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipment')]
#[ORM\Index(name: 'idx_equipment_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_equipment_facility', columns: ['facility_id'])]
#[ORM\Index(name: 'idx_equipment_type', columns: ['type'])]
#[ORM\Index(name: 'idx_equipment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_equipment_organization_type', columns: ['organization_id', 'type'])]
#[ORM\Index(name: 'idx_equipment_organization_status', columns: ['organization_id', 'status'])]
#[ORM\UniqueConstraint(name: 'uniq_equipment_organization_serial', columns: ['organization_id', 'serial_number'])]
final class EquipmentRecord
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
   * Property facilityId.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'facility_id', type: 'string', length: 36, nullable: true)]
  public ?string $facilityId = null;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'type', type: 'string', length: 32)]
  public string $type;

  /**
   * Property subType.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'sub_type', type: 'string', length: 100, nullable: true)]
  public ?string $subType = null;

  /**
   * Property brand.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'brand', type: 'string', length: 100, nullable: true)]
  public ?string $brand = null;

  /**
   * Property model.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'model', type: 'string', length: 100, nullable: true)]
  public ?string $model = null;

  /**
   * Property serialNumber.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'serial_number', type: 'string', length: 100, nullable: true)]
  public ?string $serialNumber = null;

  /**
   * Property locationLabel.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'location_label', type: 'string', length: 255, nullable: true)]
  public ?string $locationLabel = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 24)]
  public string $status = 'in_stock';

  /**
   * Property installedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'installed_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $installedAt = null;

  /**
   * Property commissionedAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'commissioned_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $commissionedAt = null;

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
  // #endregion
}
