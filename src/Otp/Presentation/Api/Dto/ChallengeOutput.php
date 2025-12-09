<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO ChallengeOutput
 * @final
 *
 * Output DTO for OTP challenge.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChallengeOutput
{
  //#region Properties
  /**
   * Property token
   *
   * The challenge token (use this for subsequent API calls).
   *
   * @var string
   */
  #[ApiProperty(
    description: 'Challenge token to use in GET/verify/resend endpoints',
    example: 'a1b2c3d4e5f6...',
  )]
  public string $token;

  /**
   * Property purpose
   *
   * The OTP purpose.
   *
   * @var string
   */
  #[ApiProperty(
    description: 'The purpose of this OTP challenge',
    example: 'login',
  )]
  public string $purpose;

  /**
   * Property channel
   *
   * The delivery channel.
   *
   * @var string
   */
  #[ApiProperty(
    description: 'The delivery channel used',
    example: 'email',
  )]
  public string $channel;

  /**
   * Property maskedRecipient
   *
   * The masked recipient for display.
   *
   * @var string
   */
  #[ApiProperty(
    description: 'Masked recipient for user-friendly display',
    example: 'jo***@example.com',
  )]
  public string $maskedRecipient;

  /**
   * Property status
   *
   * The current challenge status.
   *
   * @var string
   */
  #[ApiProperty(
    description: 'Current status: pending, verified, expired, or failed',
    example: 'pending',
    openapiContext: [
      'enum' => ['pending', 'verified', 'expired', 'failed'],
    ],
  )]
  public string $status;

  /**
   * Property expiresIn
   *
   * Seconds until expiration.
   *
   * @var int
   */
  #[ApiProperty(
    description: 'Seconds remaining until the challenge expires',
    example: 285,
  )]
  public int $expiresIn;

  /**
   * Property attemptsRemaining
   *
   * Number of verification attempts remaining.
   *
   * @var int
   */
  #[ApiProperty(
    description: 'Number of verification attempts remaining',
    example: 4,
  )]
  public int $attemptsRemaining;

  /**
   * Property canResendIn
   *
   * Seconds until resend is allowed.
   *
   * @var int
   */
  #[ApiProperty(
    description: 'Seconds until OTP can be resent (0 = can resend now)',
    example: 45,
  )]
  public int $canResendIn;
  //#endregion
}
