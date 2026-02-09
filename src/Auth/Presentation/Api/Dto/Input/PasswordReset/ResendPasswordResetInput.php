<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\PasswordReset;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ResendPasswordResetInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ResendPasswordResetInput
{
  // #region Properties
  /**
   * Property token.
   *
   * Challenge token from the request step.
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
  // #endregion
}
