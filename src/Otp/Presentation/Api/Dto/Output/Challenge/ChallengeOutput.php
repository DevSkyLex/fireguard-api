<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Output\Challenge;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ChallengeOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChallengeOutput
{
  // #region Properties
  /**
   * Property token.
   *
   * The challenge token (use this for subsequent API calls).
   */
  #[ApiProperty(
    description: 'Challenge token to use in GET/verify/resend endpoints',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'a1b2c3d4e5f6...',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $token;

  /**
   * Property purpose.
   *
   * The OTP purpose.
   */
  #[ApiProperty(
    description: 'The purpose of this OTP challenge',
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
  public string $purpose;

  /**
   * Property channel.
   *
   * The delivery channel.
   */
  #[ApiProperty(
    description: 'The delivery channel used',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'email',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $channel;

  /**
   * Property maskedRecipient.
   *
   * The masked recipient for display.
   */
  #[ApiProperty(
    description: 'Masked recipient for user-friendly display',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'jo***@example.com',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public string $maskedRecipient;

  /**
   * Property status.
   *
   * The current challenge status.
   */
  #[ApiProperty(
    description: 'Current status: pending, verified, expired, or failed',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'pending',
    openapiContext: [
      'type' => 'string',
      'enum' => ['pending', 'verified', 'expired', 'failed'],
      'readOnly' => true,
    ],
  )]
  public string $status;

  /**
   * Property expiresIn.
   *
   * Seconds until expiration.
   */
  #[ApiProperty(
    description: 'Seconds remaining until the challenge expires',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 285,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $expiresIn;

  /**
   * Property attemptsRemaining.
   *
   * Number of verification attempts remaining.
   */
  #[ApiProperty(
    description: 'Number of verification attempts remaining',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 4,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $attemptsRemaining;

  /**
   * Property canResendIn.
   *
   * Seconds until resend is allowed.
   */
  #[ApiProperty(
    description: 'Seconds until OTP can be resent (0 = can resend now)',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 45,
    openapiContext: [
      'type' => 'integer',
      'readOnly' => true,
    ],
  )]
  public int $canResendIn;
  // #endregion
}
