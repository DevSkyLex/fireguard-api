<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Record EquipmentTypeCatalogRecord.
 *
 * Persists organization overrides without coupling to another module's Record.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipment_type_catalog')]
class EquipmentTypeCatalogRecord
{
  // #region Properties
  /**
   * Property organizationId.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property value.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'type_code', type: 'string', length: 32)]
  public string $value;

  /**
   * Property label.
   */
  #[ORM\Column(type: 'string', length: 100)]
  public string $label;

  /**
   * Property family.
   */
  #[ORM\Column(type: 'string', length: 16)]
  public string $family;

  /**
   * Property archived.
   */
  #[ORM\Column(type: 'boolean')]
  public bool $archived = false;

  /**
   * Property revision.
   */
  #[ORM\Column(type: 'integer')]
  public int $revision = 1;
  // #endregion
}
