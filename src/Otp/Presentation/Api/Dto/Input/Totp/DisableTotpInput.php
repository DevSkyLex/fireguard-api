<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Input\Totp;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO DisableTotpInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DisableTotpInput
{
  // #region Properties
  /**
   * Property code.
   *
   * The current TOTP code, proving possession of the authenticator app
   * before disabling TOTP.
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'The current TOTP code from the authenticator app.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: '123456',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public string $code;
  // #endregion
}
