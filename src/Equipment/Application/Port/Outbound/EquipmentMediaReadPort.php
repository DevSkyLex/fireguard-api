<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Application\Contract\Media\EquipmentMediaView;

/**
 * Reads attachment metadata without exposing persistence records.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentMediaReadPort
{
  // #region Methods
  /**
   * @since 1.0.0 @return ?EquipmentMediaView the stored media and its scope
   */
  public function find(string $id, bool $forUpdate = false): ?EquipmentMediaView;
  // #endregion
}
