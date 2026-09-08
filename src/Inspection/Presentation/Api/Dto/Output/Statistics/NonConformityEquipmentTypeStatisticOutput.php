<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Dto\Output\Statistics;

/**
 * DTO NonConformityEquipmentTypeStatisticOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityEquipmentTypeStatisticOutput
{
  // #region Properties
  /**
   * Property type.
   *
   * The equipment type literal (e.g. `fire_extinguisher`), reached through
   * the owning inspection's equipment.
   *
   * @since 1.0.0
   */
  public string $type = '';

  /**
   * Property open.
   *
   * The type's open (`open`/`in_progress`) non-conformity count.
   *
   * @since 1.0.0
   */
  public int $open = 0;
  // #endregion
}
