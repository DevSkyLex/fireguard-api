<?php

declare(strict_types=1);

namespace User\Application\Port\Inbound;

use User\Application\Contract\EmailOwnershipResult;

/**
 * Port EmailOwnershipPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EmailOwnershipPort
{
  // #region Methods
  /**
   * Reads current mailbox proof and denies inactive or missing accounts.
   *
   * @since 1.0.0
   *
   * @param string $userId the account identifier
   *
   * @return EmailOwnershipResult the current address and possession state
   */
  public function get(string $userId): EmailOwnershipResult;

  /**
   * Confirms a proven mailbox only if the account is still active with that address.
   *
   * @since 1.0.0
   *
   * @param string $userId the account identifier
   * @param string $email the address verified by Fireguard
   */
  public function confirm(string $userId, string $email): void;
  // #endregion
}
