<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\SetupTotp;

use Shared\Application\Message\ResultMessage;

/**
 * Result SetupTotpResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetupTotpResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * SetupTotpResult class.
   *
   * @since 1.0.0
   *
   * @param string $secret the TOTP secret (base32 encoded)
   * @param string $qrCodeUri the otpauth:// URI for QR code generation
   */
  public function __construct(
    public readonly string $secret,
    public readonly string $qrCodeUri,
  ) {
  }
  // #endregion
}
