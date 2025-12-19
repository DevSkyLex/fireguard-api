<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

/**
 * DTO SetupTotpOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetupTotpOutput
{
    // #region Properties
    /**
     * Property secret.
     *
     * The TOTP secret (base32 encoded).
     */
    public string $secret;

    /**
     * Property qrCodeUri.
     *
     * The otpauth:// URI for QR code generation.
     */
    public string $qrCodeUri;
    // #endregion
}
