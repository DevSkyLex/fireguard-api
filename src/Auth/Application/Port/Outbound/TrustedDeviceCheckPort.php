<?php

declare(strict_types=1);

namespace Auth\Application\Port\Outbound;

/**
 * Port TrustedDeviceCheckPort.
 *
 * Port for checking if a device is trusted, allowing MFA bypass.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TrustedDeviceCheckPort
{
  /**
   * Method isTrusted.
   *
   * Checks if the provided device token is valid and trusted
   * for the given user.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $deviceToken the trusted device token (from cookie)
   *
   * @return bool true if the device is trusted, false otherwise
   */
  public function isTrusted(string $userId, string $deviceToken): bool;
}
