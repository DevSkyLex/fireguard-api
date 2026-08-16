<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record FacilityMetadataFieldRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'facility_metadata_fields')]
#[ORM\Index(name: 'idx_facility_metadata_field_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_facility_metadata_field_organization_key', columns: ['organization_id', 'field_key'])]
class FacilityMetadataFieldRecord
{
  // #region Properties
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  #[ORM\Column(name: 'field_key', type: 'string', length: 64)]
  public string $key;

  #[ORM\Column(name: 'label', type: 'string', length: 80)]
  public string $label;

  #[ORM\Column(name: 'field_type', type: 'string', length: 16)]
  public string $fieldType;

  /**
   * @var list<string>
   */
  #[ORM\Column(name: 'options', type: 'json')]
  public array $options = [];

  #[ORM\Column(name: 'facility_type', type: 'string', length: 24, nullable: true)]
  public ?string $facilityType = null;

  #[ORM\Column(name: 'required', type: 'boolean', options: ['default' => false])]
  public bool $required = false;

  #[ORM\Column(name: 'unit', type: 'string', length: 16, nullable: true)]
  public ?string $unit = null;

  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;
  // #endregion
}
