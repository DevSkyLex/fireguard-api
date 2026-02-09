<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Input\Challenge;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO VerifyOtpInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class VerifyOtpInput
{
  // #region Properties
  /**
   * Property code.
   *
   * The verification code.
   */
  #[Assert\NotBlank]
  #[ApiProperty(
    description: 'The OTP code to verify.',
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
