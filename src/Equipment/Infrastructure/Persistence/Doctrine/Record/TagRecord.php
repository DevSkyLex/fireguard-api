<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\Common\Collections\{ArrayCollection, Collection};
use Doctrine\ORM\Mapping as ORM;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Record TagRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipment_tag_catalog')]
#[ORM\Index(name: 'idx_tag_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_tag_organization_name', columns: ['organization_id', 'name'])]
class TagRecord
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
  #[ORM\ManyToOne(targetEntity: OrganizationRecord::class, inversedBy: 'tags')]
  #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?OrganizationRecord $organization = null;

  /**
   * Property name.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'name', type: 'string', length: 100)]
  public string $name;

  /**
   * Property createdAt.
   *
   * @since 1.0.0
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property equipmentLinks.
   *
   * @var Collection<int, EquipmentTagRecord>
   */
  #[ORM\OneToMany(mappedBy: 'tag', targetEntity: EquipmentTagRecord::class, cascade: ['remove'])]
  public Collection $equipmentLinks;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->equipmentLinks = new ArrayCollection();
  }
  // #endregion
}
