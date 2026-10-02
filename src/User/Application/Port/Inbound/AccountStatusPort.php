<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

/**
 * Port AccountStatusPort.
 *
 * Publishes a fresh account eligibility check without exposing user records
 * or identity details to capability consumers.
 *
 * @category Port
 */
interface AccountStatusPort
{
  // #region Methods
  /**
   * Method isActive.
   *
   * @param string $userId the account identifier
   *
   * @return bool false for missing, invalid or non-active accounts
   */
  public function isActive(string $userId): bool;
  // #endregion
}
