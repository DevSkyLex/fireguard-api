<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\Contract;

/**
 * Class ExportSourceState
 * Typed immutable source projection for one bounded generation.
 *
 * @category Contract
 */
final readonly class ExportSourceState
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param array<string,array<string,mixed>> $baseline current facts keyed by stable logical source
   *
   * @return void
   */
  public function __construct(public array $baseline, public ?bool $costsComplete, public ?int $incompleteCostCount)
  {
  }
  // #endregion
}
