<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Statistics;

/**
 * Contract NonConformityEquipmentTypeCount.
 *
 * Open non-conformity counts attributed to one equipment type, reached
 * through the owning inspection's equipment.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityEquipmentTypeCount
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $type the equipment type literal
   * @param int $open the open (`open`/`in_progress`) non-conformity count
   */
  public function __construct(
    public string $type,
    public int $open,
  ) {
  }
  // #endregion
}
