<?php

declare(strict_types=1);

namespace User\Application\Port\Outbound;

/**
 * Port EmailOwnershipRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EmailOwnershipRepositoryPort
{
  // #region Methods
  /**
   * Atomically records proof against the current email and active status.
   *
   * @since 1.0.0
   *
   * @param string $userId the account identifier
   * @param string $email the expected current email
   *
   * @return bool whether the condition still matched
   */
  public function confirm(string $userId, string $email): bool;
  // #endregion
}
