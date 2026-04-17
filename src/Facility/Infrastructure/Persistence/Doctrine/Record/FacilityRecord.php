<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record FacilityRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'facilities')]
#[ORM\Index(name: 'idx_facility_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_facility_parent', columns: ['parent_facility_id'])]
#[ORM\Index(name: 'idx_facility_type', columns: ['type'])]
#[ORM\Index(name: 'idx_facility_status', columns: ['status'])]
#[ORM\Index(name: 'idx_facility_organization_type', columns: ['organization_id', 'type'])]
#[ORM\Index(name: 'idx_facility_organization_status_type', columns: ['organization_id', 'status', 'type'])]
#[ORM\Index(name: 'idx_facility_organization_created_at', columns: ['organization_id', 'created_at'])]
#[ORM\UniqueConstraint(name: 'uniq_facility_organization_code', columns: ['organization_id', 'code'])]
class FacilityRecord
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
   * Property organization.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'facilities')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property parentFacility.
   *
   * @since 1.0.0
   */
  #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
  #[ORM\JoinColumn(name: 'parent_facility_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
  public ?FacilityRecord $parentFacility = null;

  /**
   * Property children.
   *
   * @var Collection<int, FacilityRecord>
   */
  #[ORM\OneToMany(mappedBy: 'parentFacility', targetEntity: self::class)]
  public Collection $children;

  /**
   * Property type.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'type', type: 'string', length: 24)]
  public string $type;

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'name', type: 'string', length: 120)]
  public string $name;

  /**
   * Property code.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'code', type: 'string', length: 80, nullable: true)]
  public ?string $code = null;

  /**
   * Property status.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'status', type: 'string', length: 20)]
  public string $status = 'active';

  /**
   * Property address.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'address', type: 'string', length: 255, nullable: true)]
  public ?string $address = null;

  /**
   * Property metadata.
   *
   * @since 1.0.0
   *
   * @var array<string, mixed>
   */
  #[ORM\Column(name: 'metadata', type: 'json')]
  public array $metadata = [];

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
   * Constructor.
   */
  public function __construct()
  {
    $this->children = new ArrayCollection();
  }
  // #endregion
}
