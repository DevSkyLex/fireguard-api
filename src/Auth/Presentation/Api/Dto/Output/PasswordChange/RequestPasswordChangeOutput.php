<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\PasswordChange;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};

/**
 * DTO RequestPasswordChangeOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RequestPasswordChangeOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether the request was successful
   * @param string $message informational message
   * @param string|null $challengeToken OTP challenge token for the confirm step
   * @param string|null $maskedRecipient masked destination (if available)
   * @param DateTimeImmutable|null $expiresAt expiration timestamp (if available)
   * @param int|null $maxAttempts max verification attempts (if available)
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
      example: 'A verification code has been sent to your email address.',
    )]
    public string $message,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'challengeToken')]
    #[ApiProperty(
      description: 'OTP challenge token for the confirm step',
      readable: true,
      writable: false,
      identifier: false,
      example: 'abc123def456',
      openapiContext: [
        'type' => 'string',
        'nullable' => true,
      ],
    )]
    public ?string $challengeToken = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'maskedRecipient')]
    #[ApiProperty(
      description: 'Masked email address the code was sent to',
      readable: true,
      writable: false,
      identifier: false,
      example: 'j***e@example.com',
      openapiContext: [
        'type' => 'string',
        'nullable' => true,
      ],
    )]
    public ?string $maskedRecipient = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'expiresAt')]
    #[ApiProperty(
      description: 'Expiration timestamp of the verification code',
      readable: true,
      writable: false,
      identifier: false,
      openapiContext: [
        'type' => 'string',
        'format' => 'date-time',
        'nullable' => true,
      ],
    )]
    public ?DateTimeImmutable $expiresAt = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'maxAttempts')]
    #[ApiProperty(
      description: 'Maximum number of verification attempts',
      readable: true,
      writable: false,
      identifier: false,
      example: 5,
      openapiContext: [
        'type' => 'integer',
        'nullable' => true,
      ],
    )]
    public ?int $maxAttempts = null,
  ) {
  }
  // #endregion
}
