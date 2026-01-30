<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Input\User;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO UserInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserInput
{
  // #region Properties
  /**
   * Property username.
   *
   * The username.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 50)]
  #[ApiProperty(
    description: 'Unique username (3-50 characters).',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'jdoe',
    openapiContext: [
      'type' => 'string',
      'minLength' => 3,
      'maxLength' => 50,
    ],
  )]
  public ?string $username = null;

  /**
   * Property email.
   *
   * The user email.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Email]
  #[ApiProperty(
    description: 'User email address.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'jane.doe@example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'email',
    ],
  )]
  public ?string $email = null;

  /**
   * Property password.
   *
   * The user password.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(min: 8)]
  #[ApiProperty(
    description: 'User password (minimum 8 characters).',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'Str0ngP@ssw0rd!',
    openapiContext: [
      'type' => 'string',
      'format' => 'password',
      'minLength' => 8,
      'writeOnly' => true,
    ],
  )]
  public ?string $password = null;

  /**
   * Property firstName.
   *
   * The first name.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[ApiProperty(
    description: 'User first name.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'Jane',
    openapiContext: [
      'type' => 'string',
      'maxLength' => 100,
    ],
  )]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * The last name.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 100)]
  #[ApiProperty(
    description: 'User last name.',
    readable: false,
    writable: true,
    required: true,
    identifier: false,
    example: 'Doe',
    openapiContext: [
      'type' => 'string',
      'maxLength' => 100,
    ],
  )]
  public ?string $lastName = null;

  /**
   * Property avatarUrl.
   *
   * The avatar URL.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Optional avatar URL.',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: 'https://cdn.example.com/avatars/jane.png',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'nullable' => true,
    ],
  )]
  public ?string $avatarUrl = null;

  /**
   * Property tenantId.
   *
   * The tenant ID.
   */
  #[Groups([UserSerializationGroup::WRITE])]
  #[ApiProperty(
    description: 'Tenant identifier (UUID).',
    readable: false,
    writable: true,
    required: false,
    identifier: false,
    example: '550e8400-e29b-41d4-a716-446655440000',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'nullable' => true,
    ],
  )]
  public ?string $tenantId = null;
  // #endregion
}
