<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record EquipmentTagRecord.
 *
 * @category Record
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipment_tag')]
#[ORM\Index(name: 'idx_equipment_tag_equipment', columns: ['equipment_id'])]
#[ORM\Index(name: 'idx_equipment_tag_tag', columns: ['tag_id'])]
class EquipmentTagRecord
{
  // #region Properties
  /**
   * Property equipment.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: EquipmentRecord::class, inversedBy: 'tagLinks')]
  #[ORM\JoinColumn(name: 'equipment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?EquipmentRecord $equipment = null;

  /**
   * Property tag.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\ManyToOne(targetEntity: TagRecord::class, inversedBy: 'equipmentLinks')]
  #[ORM\JoinColumn(name: 'tag_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
  public ?TagRecord $tag = null;
  // #endregion
}
