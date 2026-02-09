<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Config;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO PurposeOutput.
 *
 * Exposes an OTP purpose configuration
 * as returned by the API.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PurposeOutput
{
  // #region Properties
  /**
   * Property value.
   *
   * Machine-readable purpose key.
   */
  #[ApiProperty(
    description: 'The purpose identifier (use this in API requests)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'login',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $value;

  /**
   * Property label.
   *
   * Human-readable label for UI.
   */
  #[ApiProperty(
    description: 'Human-readable label for display',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Login 2FA',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $label;

  /**
   * Property ttlSeconds.
   *
   * Default time-to-live (seconds).
   */
  #[ApiProperty(
    description: 'Default time-to-live in seconds before OTP expires',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 300,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $ttlSeconds;

  /**
   * Property maxAttempts.
   *
   * Maximum allowed verification attempts.
   */
  #[ApiProperty(
    description: 'Maximum number of verification attempts allowed',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 5,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $maxAttempts;
  // #endregion
}
