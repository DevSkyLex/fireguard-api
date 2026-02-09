<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Output\PasswordReset;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};

/**
 * DTO ConfirmPasswordResetOutput.
 *
 * @category Output DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmPasswordResetOutput
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param bool $success whether the reset was successful
   * @param string $message informational message
   * @param string|null $errorCode error code when failed
   * @param int $attemptsRemaining remaining verification attempts
   */
  public function __construct(
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'success')]
    #[ApiProperty(
      description: 'Whether the password was reset successfully',
      readable: true,
      writable: false,
      identifier: false,
      example: true,
    )]
    public bool $success,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'message')]
    #[ApiProperty(
      description: 'Informational or error message',
      readable: true,
      writable: false,
      identifier: false,
      example: 'Password has been reset successfully.',
    )]
    public string $message,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'errorCode')]
    #[ApiProperty(
      description: 'Error code when the operation failed',
      readable: true,
      writable: false,
      identifier: false,
      example: null,
      openapiContext: [
        'type' => 'string',
        'nullable' => true,
        'enum' => ['invalid_code', 'expired', 'max_attempts_exceeded', 'invalid_token'],
      ],
    )]
    public ?string $errorCode = null,
    #[Groups(groups: [AuthSerializationGroup::TOKEN_READ])]
    #[SerializedName(serializedName: 'attemptsRemaining')]
    #[ApiProperty(
      description: 'Number of verification attempts remaining',
      readable: true,
      writable: false,
      identifier: false,
      example: 0,
    )]
    public int $attemptsRemaining = 0,
  ) {
  }
  // #endregion
}
