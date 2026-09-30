<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Event;

/** Invalidation fact: consumers must reload current published equipment state. */
final readonly class EquipmentChangedEvent
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Signals that consumers must reload the organization's current published equipment state.
   *
   * @access public
   *
   * @param string $organizationId organization owning the changed equipment
   * @param string $equipmentId identifier of the equipment whose published state changed
   *
   * @return void
   */
  public function __construct(public string $organizationId, public string $equipmentId)
  {
  }
  // #endregion
}
