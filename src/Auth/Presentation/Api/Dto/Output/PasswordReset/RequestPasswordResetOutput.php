<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\PasswordReset;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};

/**
 * DTO RequestPasswordResetOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordResetOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether the request was successful
   * @param string $message informational message
   * @param string|null $challengeToken OTP challenge token (if available)
   * @param string|null $maskedRecipient masked destination (if available)
   * @param DateTimeImmutable|null $expiresAt expiration timestamp (if available)
   * @param int|null $maxAttempts max verification attempts (if available)
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public function __construct(
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'success')]
    #[ApiProperty(
      description: 'Whether the request was processed successfully',
      readable: true,
      writable: false,
      identifier: false,
      example: true,
    )]
    public bool $success,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'message')]
    #[ApiProperty(
      description: 'Informational message',
      readable: true,
      writable: false,
      identifier: false,
      example: 'If an account exists with this email, you will receive a password reset code.',
    )]
    public string $message,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'challengeToken')]
    #[ApiProperty(
      description: 'OTP challenge token for the confirm step (if available)',
      readable: true,
      writable: false,
      identifier: false,
      example: 'abc123def456',
      openapiContext: [
        'type' => 'string',
        'nullable' => true,
        'readOnly' => true,
      ],
      jsonSchemaContext: [
        'type' => 'string',
        'nullable' => true,
        'readOnly' => true,
      ],
    )]
    public ?string $challengeToken = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'maskedRecipient')]
    #[ApiProperty(
      description: 'Masked destination where the reset code was sent (if available)',
      readable: true,
      writable: false,
      identifier: false,
      example: 'j***e@e****e.com',
      openapiContext: [
        'type' => 'string',
        'nullable' => true,
        'readOnly' => true,
      ],
      jsonSchemaContext: [
        'type' => 'string',
        'nullable' => true,
        'readOnly' => true,
      ],
    )]
    public ?string $maskedRecipient = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'expiresAt')]
    #[ApiProperty(
      description: 'Timestamp when the reset code expires (if available)',
      readable: true,
      writable: false,
      identifier: false,
      example: '2024-01-01T12:34:56+00:00',
      openapiContext: [
        'type' => 'string',
        'format' => 'date-time',
        'nullable' => true,
        'readOnly' => true,
      ],
      jsonSchemaContext: [
        'type' => 'string',
        'format' => 'date-time',
        'nullable' => true,
        'readOnly' => true,
      ],
    )]
    public ?DateTimeImmutable $expiresAt = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'maxAttempts')]
    #[ApiProperty(
      description: 'Maximum verification attempts allowed (if available)',
      readable: true,
      writable: false,
      identifier: false,
      example: 5,
      openapiContext: [
        'type' => 'integer',
        'nullable' => true,
        'readOnly' => true,
      ],
      jsonSchemaContext: [
        'type' => 'integer',
        'nullable' => true,
        'readOnly' => true,
      ],
    )]
    public ?int $maxAttempts = null,

    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'canResendIn')]
    #[ApiProperty(
      description: 'Seconds until resend is allowed',
      readable: true,
      writable: false,
      identifier: false,
      example: 60,
      openapiContext: [
        'type' => 'integer',
        'nullable' => true,
        'readOnly' => true,
      ],
      jsonSchemaContext: [
        'type' => 'integer',
        'nullable' => true,
        'readOnly' => true,
      ],
    )]
    public ?int $canResendIn = null,
  ) {
  }
  // #endregion
}
