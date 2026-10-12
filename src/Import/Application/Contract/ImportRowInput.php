<?php

declare(strict_types=1);

namespace Import\Application\Contract;

/**
 * Contract ImportRowInput
 *
 * Carries one decoded source row with its original data-row number.
 *
 * @category Contract
 */
final readonly class ImportRowInput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param int $rowNumber one-based original data-row position
   * @param array<string, string> $row decoded CSV cells, with unknown columns retained
   *
   * @return void
   */
  public function __construct(public int $rowNumber, public array $row)
  {
  }
  // #endregion
}
