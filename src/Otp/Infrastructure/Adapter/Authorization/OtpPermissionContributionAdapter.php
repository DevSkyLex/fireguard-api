<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Authorization;

use Authorization\Application\Contract\Catalog\PermissionDefinition;
use Authorization\Application\Port\Inbound\PermissionCatalogContributionPort;

/**
 * Class OtpPermissionContributionAdapter
 *
 * Publishes Otp-owned global permission vocabulary through Authorization's pure contract.
 *
 * @category Adapter
 */
final readonly class OtpPermissionContributionAdapter implements PermissionCatalogContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * Keeps persisted keys, descriptions and publication order stable.
   *
   * @access public
   *
   * @return list<PermissionDefinition> owner vocabulary
   */
  public static function definitions(): array
  {
    return [
      new PermissionDefinition('otp_config.read', 'View OTP configuration', 23),
      new PermissionDefinition('otp_challenges.create', 'Create OTP challenges', 24),
      new PermissionDefinition('otp_challenges.read', 'View OTP challenges', 25),
      new PermissionDefinition('otp_challenges.verify', 'Verify OTP challenges', 26),
      new PermissionDefinition('otp_challenges.resend', 'Resend OTP challenges', 27),
      new PermissionDefinition('otp_challenges.*', 'All OTP challenge operations', 28),
      new PermissionDefinition('otp_totp.setup', 'Setup TOTP', 29),
      new PermissionDefinition('otp_totp.confirm', 'Confirm TOTP setup', 30),
      new PermissionDefinition('otp_totp.disable', 'Disable TOTP', 31),
    ];
  }
  // #endregion
}
