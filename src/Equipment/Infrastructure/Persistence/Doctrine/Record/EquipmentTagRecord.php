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
final class EquipmentTagRecord
{
  // #region Properties
  /**
   * Property equipmentId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'equipment_id', type: 'string', length: 36)]
  public string $equipmentId;

  /**
   * Property tagId.
   *
   * @since 1.0.0
   */
  #[ORM\Id]
  #[ORM\Column(name: 'tag_id', type: 'string', length: 36)]
  public string $tagId;
  // #endregion
}
