<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

/**
 * Client context passed to the session issuer after a federated callback.
 */
final readonly class FederatedLoginContext
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries request and trusted-device details used while completing a federated login.
   *
   * @access public
   *
   * @param ?string $ipAddress request IP address, when available
   * @param ?string $userAgent request user-agent value, when available
   * @param ?string $trustedDeviceToken trusted-device token supplied for this login, when present
   *
   * @return void
   */
  public function __construct(
    public ?string $ipAddress,
    public ?string $userAgent,
    public ?string $trustedDeviceToken,
  ) {
  }
  // #endregion
}
