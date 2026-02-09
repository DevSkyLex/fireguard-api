<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Dto\Input\Challenge;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO CreateChallengeInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateChallengeInput
{
  // #region Properties
  /**
   * Property purpose.
   *
   * The OTP purpose.
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['login', 'password_reset', 'email_verification', 'phone_verification', 'sensitive_operation', 'transaction_approval'])]
  #[ApiProperty(
    description: 'The purpose of the OTP challenge. Use GET /api/otp/purposes to list available values.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'login',
    openapiContext: [
      'type' => 'string',
      'enum' => ['login', 'password_reset', 'email_verification', 'phone_verification', 'sensitive_operation', 'transaction_approval'],
    ],
  )]
  public string $purpose;

  /**
   * Property channel.
   *
   * The delivery channel.
   */
  #[Assert\NotBlank]
  #[Assert\Choice(choices: ['email', 'sms', 'totp'])]
  #[ApiProperty(
    description: 'The delivery channel for the OTP. Use GET /api/otp/channels to list available values.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'email',
    openapiContext: [
      'type' => 'string',
      'enum' => ['email', 'sms', 'totp'],
    ],
  )]
  public string $channel;

  /**
   * Property recipient.
   *
   * The recipient (email or phone). Optional if using user's default.
   */
  #[ApiProperty(
    description: 'The recipient email or phone number. If not provided, user\'s default will be used.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'user@example.com',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
    ],
  )]
  public ?string $recipient = null;

  /**
   * Property ttlSeconds.
   *
   * Custom TTL in seconds.
   */
  #[Assert\Range(min: 60, max: 3600)]
  #[ApiProperty(
    description: 'Custom time-to-live in seconds. Defaults to purpose-specific value.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 300,
    openapiContext: [
      'type' => 'integer',
      'minimum' => 60,
      'maximum' => 3600,
      'nullable' => true,
    ],
  )]
  public ?int $ttlSeconds = null;

  /**
   * Property context.
   *
   * Optional business context data.
   *
   * @var array<string, mixed>|null
   */
  #[ApiProperty(
    description: 'Optional context data (transaction ID, description, etc.)',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: ['transactionId' => 'TXN-12345', 'description' => 'Confirm transfer of $100'],
    openapiContext: [
      'type' => 'object',
      'nullable' => true,
      'additionalProperties' => true,
    ],
  )]
  public ?array $context = null;
  // #endregion
}
