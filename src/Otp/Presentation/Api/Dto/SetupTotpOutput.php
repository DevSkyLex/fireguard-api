<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

/**
 * DTO SetupTotpOutput
 * @final
 *
 * Output DTO for TOTP setup.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SetupTotpOutput
{
  //#region Properties
  /**
   * Property secret
   *
   * The TOTP secret (base32 encoded).
   *
   * @var string
   */
  public string $secret;

  /**
   * Property qrCodeUri
   *
   * The otpauth:// URI for QR code generation.
   *
   * @var string
   */
  public string $qrCodeUri;
  //#endregion
}
