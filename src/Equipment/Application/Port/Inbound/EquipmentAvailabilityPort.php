<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Inbound;

/** Answers whether an organization has published equipment. @category Port @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
interface EquipmentAvailabilityPort
{
  /**
   * @since 1.0.0
   */
  public function hasEquipment(string $organizationId): bool;
}
