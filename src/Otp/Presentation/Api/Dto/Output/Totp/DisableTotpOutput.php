<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Totp;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO DisableTotpOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DisableTotpOutput
{
  // #region Properties
  /**
   * Property success.
   *
   * Whether TOTP was successfully disabled.
   */
  #[ApiProperty(
    description: 'Whether TOTP was successfully disabled.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
  )]
  public bool $success;
  // #endregion
}
