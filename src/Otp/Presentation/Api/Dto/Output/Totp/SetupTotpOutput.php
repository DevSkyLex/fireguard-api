<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Totp;

use ApiPlatform\Metadata\ApiProperty;

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
   * Base32 secret for authenticator enrollment.
   * Treat as sensitive.
   */
  #[ApiProperty(
    description: 'Base32-encoded TOTP secret for the authenticator app',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'JBSWY3DPEHPK3PXP',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $secret;

  /**
   * Property qrCodeUri.
   *
   * The otpauth:// URI used to render a QR code.
   */
  #[ApiProperty(
    description: 'otpauth:// URI to generate the QR code',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'otpauth://totp/Example:alice@example.com?secret=JBSWY3DPEHPK3PXP&issuer=Example',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $qrCodeUri;
  // #endregion
}
