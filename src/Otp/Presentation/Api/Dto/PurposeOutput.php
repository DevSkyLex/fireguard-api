<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO PurposeOutput.
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
   * The purpose identifier.
   */
  #[ApiProperty(
    description: 'The purpose identifier (use this in API requests)',
    example: 'login',
  )]
  public string $value;

  /**
   * Property label.
   *
   * Human-readable label.
   */
  #[ApiProperty(
    description: 'Human-readable label for display',
    example: 'Login 2FA',
  )]
  public string $label;

  /**
   * Property ttlSeconds.
   *
   * Default time-to-live in seconds.
   */
  #[ApiProperty(
    description: 'Default time-to-live in seconds before OTP expires',
    example: 300,
  )]
  public int $ttlSeconds;

  /**
   * Property maxAttempts.
   *
   * Default maximum verification attempts.
   */
  #[ApiProperty(
    description: 'Maximum number of verification attempts allowed',
    example: 5,
  )]
  public int $maxAttempts;
  // #endregion
}
