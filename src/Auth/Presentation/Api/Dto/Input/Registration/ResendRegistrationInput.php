<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\Registration;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ResendRegistrationInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ResendRegistrationInput
{
  // #region Properties
  /**
   * Property token.
   *
   * Challenge token received from the registration step.
   *
   * @example abc123def456...
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The token field is required.')]
  #[Groups(groups: [AuthSerializationGroup::REGISTRATION_WRITE])]
  #[SerializedName(serializedName: 'token')]
  #[ApiProperty(
    description: 'Challenge token from the registration request',
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
