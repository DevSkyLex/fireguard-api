<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound;

use Otp\Domain\ValueObject\TotpSecret;

/**
 * Port TotpServicePort
 *
 * Outbound port for TOTP operations.
 *
 * @category Port
 * @package Otp\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpServicePort
{
  //#region Methods
  /**
   * Method generateSecret
   *
   * Generates a new TOTP secret.
   *
   * @access public
   * @since 1.0.0
   *
   * @return TotpSecret The generated secret.
   */
  public function generateSecret(): TotpSecret;

  /**
   * Method verify
   *
   * Verifies a TOTP code against a secret.   
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $code The input code.
   * @param TotpSecret $secret The secret.
   *
   * @return bool True if valid.
   */
  public function verify(string $code, TotpSecret $secret): bool;

  /**
   * Method getProvisioningUri
   *
   * Returns the provisioning URI for QR code generation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TotpSecret $secret The secret.
   * @param string $accountName The account name/email.
   * @param string $issuer The issuer name.
   *
   * @return string The otpauth:// URI.
   */
  public function getProvisioningUri(
    TotpSecret $secret,
    string $accountName,
    string $issuer = 'FireGuard Auth'
  ): string;
  //#endregion
}
