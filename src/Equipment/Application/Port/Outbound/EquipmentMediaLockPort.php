<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

/** Acquires media owner locks inside its root transaction. @category Port @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
interface EquipmentMediaLockPort
{
  /**
   * @since 1.0.0
   */
  public function lock(string $equipmentId): void;
}
