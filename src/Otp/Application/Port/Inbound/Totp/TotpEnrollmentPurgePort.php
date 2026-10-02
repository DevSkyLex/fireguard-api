<?php

declare(strict_types=1);

namespace Otp\Application\Port\Inbound\Totp;

/**
 * Port TotpEnrollmentPurgePort.
 *
 * Removes authenticator secrets and enrollment state when the owning account is deleted.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpEnrollmentPurgePort
{
  // #region Methods
  /**
   * Method withUserLock.
   *
   * Holds the auth transaction and enrollment mutation lock through account deletion and purge.
   *
   * @access public
   *
   * @template T
   *
   * @param string $userId the account identifier
   * @param callable():T $operation the auth-only deletion operation
   *
   * @return T the operation result
   */
  public function withUserLock(string $userId, callable $operation): mixed;

  /**
   * Method purgeForUser.
   *
   * @since 1.0.0
   *
   * @param string $userId the deleted account's identifier
   *
   * @return void
   */
  public function purgeForUser(string $userId): void;
  // #endregion
}
