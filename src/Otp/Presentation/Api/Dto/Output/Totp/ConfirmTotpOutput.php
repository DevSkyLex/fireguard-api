<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Totp;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ConfirmTotpOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConfirmTotpOutput
{
  // #region Properties
  /**
   * Property success.
   *
   * Whether TOTP was successfully activated.
   */
  #[ApiProperty(
    description: 'Whether TOTP was successfully activated.',
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
