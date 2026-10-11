<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Interface FileCleanupPort
 *
 * Records file deletion in its owning database's outbox with the SQL mutation; delivery occurs after commit.
 *
 * @category Outbound Port
 */
interface FileCleanupPort
{
  // #region Methods
  /**
   * Method request
   *
   * Records an idempotent deletion request. Callers must hold the owning database transaction.
   *
   * @access public
   *
   * @param string $storagePath the obsolete storage key, never the winning key of another attempt
   *
   * @return void
   */
  public function request(string $storagePath): void;
  // #endregion
}
