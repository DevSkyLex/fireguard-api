<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\PasswordReset;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ConfirmPasswordResetInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConfirmPasswordResetInput
{
  // #region Properties
  /**
   * Property token.
   *
   * Challenge token received from the request step.
   *
   * @example abc123def456...
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[Groups(groups: [AuthSerializationGroup::PASSWORD_RESET_WRITE])]
  #[SerializedName(serializedName: 'token')]
  #[ApiProperty(
    description: 'Challenge token from the password reset request',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'abc123def456',
    openapiContext: [
      'type' => 'string',
      'minLength' => 16,
      'maxLength' => 255,
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $token = null;

  /**
   * Property code.
   *
   * OTP code received by email.
   *
   * @example 123456
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The code field is required.')]
  #[Assert\Length(
    min: 4,
    max: 10,
    minMessage: 'Code must be at least {{ limit }} characters.',
    maxMessage: 'Code cannot exceed {{ limit }} characters.',
  )]
  #[Groups(groups: [AuthSerializationGroup::PASSWORD_RESET_WRITE])]
  #[SerializedName(serializedName: 'code')]
  #[ApiProperty(
    description: 'OTP verification code received by email',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: '123456',
    openapiContext: [
      'type' => 'string',
      'minLength' => 4,
      'maxLength' => 10,
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $code = null;

  /**
   * Property newPassword.
   *
   * New password for the account.
   *
   * @example SecureP@ssw0rd!
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The new password field is required.')]
  #[Assert\Length(
    min: 8,
    max: 128,
    minMessage: 'Password must be at least {{ limit }} characters long.',
    maxMessage: 'Password cannot exceed {{ limit }} characters.',
  )]
  #[Assert\Regex(
    pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]/',
    message: 'Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.',
  )]
  #[Groups(groups: [AuthSerializationGroup::PASSWORD_RESET_WRITE])]
  #[SerializedName(serializedName: 'newPassword')]
  #[ApiProperty(
    description: 'New password (must meet security requirements)',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'SecureP@ssw0rd!',
    openapiContext: [
      'type' => 'string',
      'format' => 'password',
      'minLength' => 8,
      'maxLength' => 128,
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'password',
      'writeOnly' => true,
    ],
  )]
  public ?string $newPassword = null;
  // #endregion
}
