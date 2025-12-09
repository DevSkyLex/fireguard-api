<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateChallengeInput
 * @final
 *
 * Input DTO for creating an OTP challenge.
 *
 * @category DTO
 * @package Otp\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateChallengeInput
{
  //#region Properties
  /**
   * Property purpose
   *
   * The OTP purpose.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['login', 'password_reset', 'email_verification', 'phone_verification', 'sensitive_operation', 'transaction_approval'])]
  #[ApiProperty(
    description: 'The purpose of the OTP challenge. Use GET /api/otp/purposes to list available values.',
    example: 'login',
    openapiContext: [
      'enum' => ['login', 'password_reset', 'email_verification', 'phone_verification', 'sensitive_operation', 'transaction_approval'],
    ],
  )]
  public string $purpose;

  /**
   * Property channel
   *
   * The delivery channel.
   *
   * @var string
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['email', 'sms', 'totp'])]
  #[ApiProperty(
    description: 'The delivery channel for the OTP. Use GET /api/otp/channels to list available values.',
    example: 'email',
    openapiContext: [
      'enum' => ['email', 'sms', 'totp'],
    ],
  )]
  public string $channel;

  /**
   * Property recipient
   *
   * The recipient (email or phone). Optional if using user's default.
   *
   * @var string|null
   */
  #[ApiProperty(
    description: 'The recipient email or phone number. If not provided, user\'s default will be used.',
    example: 'user@example.com',
  )]
  public ?string $recipient = null;

  /**
   * Property ttlSeconds
   *
   * Custom TTL in seconds.
   *
   * @var int|null
   */
  #[Assert\Range(min: 60, max: 3600)]
  #[ApiProperty(
    description: 'Custom time-to-live in seconds. Defaults to purpose-specific value.',
    example: 300,
  )]
  public ?int $ttlSeconds = null;

  /**
   * Property context
   *
   * Optional business context data.
   *
   * @var array<string, mixed>|null
   */
  #[ApiProperty(
    description: 'Optional context data (transaction ID, description, etc.)',
    example: ['transactionId' => 'TXN-12345', 'description' => 'Confirm transfer of $100'],
  )]
  public ?array $context = null;
  //#endregion
}
