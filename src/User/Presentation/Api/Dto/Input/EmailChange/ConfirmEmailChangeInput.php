<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Input\EmailChange;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO ConfirmEmailChangeInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ConfirmEmailChangeInput
{
  // #region Properties
  /**
   * Property token.
   *
   * The confirmation token received by email at the new address.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[Assert\Length(max: 128, maxMessage: 'The token must not exceed 128 characters.')]
  #[Groups(groups: [UserSerializationGroup::EMAIL_CHANGE_WRITE])]
  #[SerializedName(serializedName: 'token')]
  #[ApiProperty(
    description: 'Confirmation token from the email sent to the new address',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
    openapiContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
    jsonSchemaContext: [
      'type' => 'string',
      'writeOnly' => true,
    ],
  )]
  public ?string $token = null;
  // #endregion
}
