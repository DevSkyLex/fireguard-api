<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Challenge;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO VerifyOtpOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class VerifyOtpOutput
{
  // #region Properties
  /**
   * Property success.
   *
   * Whether verification was successful.
   */
  #[ApiProperty(
    description: 'Whether verification was successful',
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

  /**
   * Property attemptsRemaining.
   *
   * Remaining verification attempts.
   */
  #[ApiProperty(
    description: 'Remaining verification attempts before the challenge is locked',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 2,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $attemptsRemaining;

  /**
   * Property error.
   *
   * Error message if failed.
   */
  #[ApiProperty(
    description: 'Error message when verification fails',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'Invalid OTP code',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $error = null;
  // #endregion
}
