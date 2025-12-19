<?php

declare(strict_types=1);

namespace Otp\Application\Port\Outbound;

use Otp\Domain\ValueObject\TotpSecret;

/**
 * Port TotpServicePort.
 *
 * Outbound port for TOTP operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TotpServicePort
{
    // #region Methods
    /**
     * Method generateSecret.
     *
     * Generates a new TOTP secret.
     *
     * @since 1.0.0
     *
     * @return TotpSecret the generated secret
     */
    public function generateSecret(): TotpSecret;

    /**
     * Method verify.
     *
     * Verifies a TOTP code against a secret.
     *
     * @since 1.0.0
     *
     * @param string     $code   the input code
     * @param TotpSecret $secret the secret
     *
     * @return bool true if valid
     */
    public function verify(string $code, TotpSecret $secret): bool;

    /**
     * Method getProvisioningUri.
     *
     * Returns the provisioning URI for QR code generation.
     *
     * @since 1.0.0
     *
     * @param TotpSecret $secret      the secret
     * @param string     $accountName the account name/email
     * @param string     $issuer      the issuer name
     *
     * @return string the otpauth:// URI
     */
    public function getProvisioningUri(
        TotpSecret $secret,
        string $accountName,
        string $issuer = 'FireGuard Auth',
    ): string;
    // #endregion
}
