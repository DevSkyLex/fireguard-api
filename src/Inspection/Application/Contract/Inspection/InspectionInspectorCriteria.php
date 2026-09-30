<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Inspection;

/**
 * Inspector identity and type filters for inspection reads.
 */
final readonly class InspectionInspectorCriteria
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups inspector identity and inspector-type filters for inspection reads.
   *
   * @access public
   *
   * @param ?string $userId optional inspector user identifier
   * @param ?string $type optional inspector type filter
   *
   * @return void
   */
  public function __construct(
    public ?string $userId = null,
    public ?string $type = null,
  ) {
  }
  // #endregion
}
