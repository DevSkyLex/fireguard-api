<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\PasswordChange;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO RequestPasswordChangeInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RequestPasswordChangeInput
{
  // #region Properties
  /**
   * Property currentPassword.
   *
   * Current password of the authenticated user.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The current password field is required.')]
  #[Groups(groups: [AuthSerializationGroup::PASSWORD_CHANGE_WRITE])]
  #[SerializedName(serializedName: 'currentPassword')]
  #[ApiProperty(
    description: 'Current password, verified before the OTP code is sent',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'CurrentP@ssw0rd!',
    openapiContext: [
      'type' => 'string',
      'format' => 'password',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'password',
      'writeOnly' => true,
    ],
  )]
  public ?string $currentPassword = null;
  // #endregion
}
