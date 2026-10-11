<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Facility;

/**
 * Contract CanonicalFacilityPage.
 *
 * Carries matching rows and their total under identical filters.
 *
 * @category Contract
 */
final readonly class CanonicalFacilityPage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param list<CanonicalFacilityCollectionItem> $items the page rows
   */
  public function __construct(public array $items, public int $total)
  {
  }
  // #endregion
}
