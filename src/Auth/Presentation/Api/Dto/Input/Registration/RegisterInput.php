<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto\Input\Registration;

use ApiPlatform\Metadata\ApiProperty;
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\{Groups, SerializedName};
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO RegisterInput.
 *
 * @category Input DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RegisterInput
{
  // #region Properties
  /**
   * Property firstName.
   *
   * The new user's first name.
   *
   * @example Jane
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The first name field is required.')]
  #[Assert\Length(max: 100, maxMessage: 'First name cannot exceed {{ limit }} characters.')]
  #[Groups(groups: [AuthSerializationGroup::REGISTRATION_WRITE])]
  #[SerializedName(serializedName: 'firstName')]
  #[ApiProperty(
    description: 'First name of the new user',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'Jane',
    openapiContext: [
      'type' => 'string',
      'maxLength' => 100,
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * The new user's last name.
   *
   * @example Doe
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The last name field is required.')]
  #[Assert\Length(max: 100, maxMessage: 'Last name cannot exceed {{ limit }} characters.')]
  #[Groups(groups: [AuthSerializationGroup::REGISTRATION_WRITE])]
  #[SerializedName(serializedName: 'lastName')]
  #[ApiProperty(
    description: 'Last name of the new user',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'Doe',
    openapiContext: [
      'type' => 'string',
      'maxLength' => 100,
    ],
    jsonSchemaContext: [
      'type' => 'string',
    ],
  )]
  public ?string $lastName = null;

  /**
   * Property email.
   *
   * Email address of the new user (also used to derive the username).
   *
   * @example jane.doe@example.com
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The email field is required.')]
  #[Assert\Email(message: 'The email must be a valid email address.')]
  #[Assert\Length(max: 255, maxMessage: 'Email cannot exceed {{ limit }} characters.')]
  #[Groups(groups: [AuthSerializationGroup::REGISTRATION_WRITE])]
  #[SerializedName(serializedName: 'email')]
  #[ApiProperty(
    description: 'Email address of the new user',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    default: null,
    example: 'jane.doe@example.com',
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

  /**
   * Property password.
   *
   * The account password (must meet security requirements).
   *
   * @example SecureP@ssw0rd!
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'The password field is required.')]
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
  #[Groups(groups: [AuthSerializationGroup::REGISTRATION_WRITE])]
  #[SerializedName(serializedName: 'password')]
  #[ApiProperty(
    description: 'Account password (must meet security requirements)',
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
  public ?string $password = null;
  // #endregion
}
