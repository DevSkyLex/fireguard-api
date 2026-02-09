<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\PasswordReset;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO RequestPasswordResetInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RequestPasswordResetInput
{
  // #region Properties
  /**
   * Property email.
   *
   * Email address of the user requesting password reset.
   *
   * @example john.doe@example.com
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The email field is required.')]
  #[Assert\Email(message: 'The email must be a valid email address.')]
  #[Groups(groups: [AuthSerializationGroup::PASSWORD_RESET_WRITE])]
  #[SerializedName(serializedName: 'email')]
  #[ApiProperty(
    description: 'Email address of the user requesting password reset',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'john.doe@example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'email',
      'minLength' => 5,
      'maxLength' => 255,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'email',
    ],
  )]
  public ?string $email = null;
  // #endregion
}
