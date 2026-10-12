<?php

declare(strict_types=1);

namespace Import\Application\Port\Outbound;

use Import\Application\Contract\{ImportRowInput, ImportRowOutcome};

/**
 * Interface ImportRowSessionPort
 *
 * Processes rows inside the engine's leased main transaction. Owner writes and the
 * engine's row receipt commit together; technical exceptions roll back that row.
 *
 * @category Port
 */
interface ImportRowSessionPort
{
  // #region Methods
  /**
   * Method processRow
   *
   * @access public
   *
   * @param ImportRowInput $input decoded original source row
   *
   * @return ImportRowOutcome safe success or business rejection
   */
  public function processRow(ImportRowInput $input): ImportRowOutcome;

  /**
   * Method restoreConfirmedSimulationRow
   *
   * Reconstructs only job-local projection from a confirmed successful simulation.
   * It must never provision, consume quotas, emit events or record durable mutations.
   *
   * @access public
   *
   * @param ImportRowInput $input confirmed source row in original file order
   *
   * @return void
   */
  public function restoreConfirmedSimulationRow(ImportRowInput $input): void;
  // #endregion
}
