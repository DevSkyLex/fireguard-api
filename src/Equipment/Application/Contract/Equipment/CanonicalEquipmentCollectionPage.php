<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Equipment;

/**
 * Bounded collection page with the unpaginated matching total.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalEquipmentCollectionPage
{
  // #region Constructor
  /**
   * @param list<CanonicalEquipmentCollectionItem> $items @since 1.0.0
   */
  public function __construct(public array $items, public int $total, public int $page, public int $itemsPerPage)
  {
  }
  // #endregion
}
