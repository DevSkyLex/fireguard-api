<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\Registration;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};

/**
 * DTO RegisterOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether the registration request was accepted
   * @param string $message informational message
   * @param string|null $challengeToken OTP challenge token for the verify step
   * @param string|null $maskedRecipient masked destination where the code was sent
   * @param DateTimeImmutable|null $expiresAt expiration timestamp
   * @param int|null $maxAttempts max verification attempts
   * @param int|null $canResendIn seconds until resend is allowed
   */
  public function __construct(
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'success')]
    #[ApiProperty(
      description: 'Whether the registration request was accepted',
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
      example: 'Your account has been created. Enter the verification code we sent to your email.',
    )]
    public string $message,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'challengeToken')]
    #[ApiProperty(
      description: 'OTP challenge token for the verify step',
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
      description: 'Masked destination where the verification code was sent',
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
      description: 'Timestamp when the verification code expires',
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
      description: 'Maximum verification attempts allowed',
      readable: true,
      writable: false,
      identifier: false,
      example: 10,
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
      description: 'Seconds until the verification code can be resent',
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
