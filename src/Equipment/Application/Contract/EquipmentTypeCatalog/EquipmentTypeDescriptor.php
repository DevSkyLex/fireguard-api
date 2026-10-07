<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\EquipmentTypeCatalog;

/**
 * Contract EquipmentTypeDescriptor.
 *
 * Stable descriptor shared by catalog reads and equipment-facing projections.
 *
 * @category Contract
 */
final readonly class EquipmentTypeDescriptor
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $value stable equipment type code
   * @param string $label display label
   * @param string $family fire, safety or other
   * @param bool $archived whether unavailable for new equipment
   * @param int $revision optimistic descriptor revision
   *
   * @return void
   */
  public function __construct(
    public string $value,
    public string $label,
    public string $family,
    public bool $archived,
    public int $revision,
  ) {
  }
  // #endregion
}
