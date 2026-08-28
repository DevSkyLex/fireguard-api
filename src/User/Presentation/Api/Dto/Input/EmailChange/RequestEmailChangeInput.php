<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Input\EmailChange;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO RequestEmailChangeInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RequestEmailChangeInput
{
  // #region Properties
  /**
   * Property newEmail.
   *
   * The requested new sign-in email address.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The new email field is required.')]
  #[Assert\Email(message: 'The new email must be a valid email address.')]
  #[Assert\Length(max: 320, maxMessage: 'The new email must not exceed 320 characters.')]
  #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_WRITE])]
  #[SerializedName(serializedName: 'newEmail')]
  #[ApiProperty(
    description: 'The new sign-in email address; a confirmation link is sent to it',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'new-address@example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'email',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'format' => 'email',
      'writeOnly' => true,
    ],
  )]
  public ?string $newEmail = null;

  /**
   * Property currentPassword.
   *
   * Current password of the authenticated user.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The current password field is required.')]
  #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_WRITE])]
  #[SerializedName(serializedName: 'currentPassword')]
  #[ApiProperty(
    description: 'Current password, verified before the confirmation email is sent',
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
