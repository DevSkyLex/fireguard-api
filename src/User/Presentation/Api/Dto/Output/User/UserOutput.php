<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\User;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO UserOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The user ID.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'User identifier (UUID).',
    readable: true,
    writable: false,
    required: true,
    identifier: true,
    example: '550e8400-e29b-41d4-a716-446655440000',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'readOnly' => true,
    ],
  )]
  public ?string $id = null;

  /**
   * Property username.
   *
   * The username.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Username of the user.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'jdoe',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public ?string $username = null;

  /**
   * Property email.
   *
   * The user email.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'User email address.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'jane.doe@example.com',
    openapiContext: [
      'type' => 'string',
      'format' => 'email',
      'readOnly' => true,
    ],
  )]
  public ?string $email = null;

  /**
   * Property firstName.
   *
   * The first name.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'User first name.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Jane',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * The last name.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'User last name.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: 'Doe',
    openapiContext: [
      'type' => 'string',
      'readOnly' => true,
    ],
  )]
  public ?string $lastName = null;

  /**
   * Property avatarUrl.
   *
   * The avatar URL.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Avatar URL.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'https://cdn.example.com/avatars/jane.png',
    openapiContext: [
      'type' => 'string',
      'format' => 'uri',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $avatarUrl = null;

  /**
   * Property status.
   *
   * The user status.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Current user status.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: 'active',
    openapiContext: [
      'type' => 'string',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $status = null;

  /**
   * Property emailVerified.
   *
   * Whether the email is verified.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Whether the email is verified.',
    readable: true,
    writable: false,
    required: true,
    identifier: false,
    example: true,
    openapiContext: [
      'type' => 'boolean',
      'readOnly' => true,
    ],
  )]
  public bool $emailVerified = false;

  /**
   * Property tenantId.
   *
   * The tenant ID.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Tenant identifier (UUID).',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: '550e8400-e29b-41d4-a716-446655440000',
    openapiContext: [
      'type' => 'string',
      'format' => 'uuid',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $tenantId = null;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime when the user was created.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: '2026-01-29T10:15:30+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $createdAt = null;

  /**
   * Property lastLoginAt.
   *
   * The last login timestamp.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'ISO 8601 datetime of the last login.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: '2026-01-29T12:01:00+00:00',
    openapiContext: [
      'type' => 'string',
      'format' => 'date-time',
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public ?string $lastLoginAt = null;
  // #endregion

  // #region Methods
  /**
   * Method getAvatarUrls.
   *
   * Derives the per-size avatar URLs from the canonical avatar URL.
   * Returns null when no avatar is set or when the URL does not point
   * to the internal avatar endpoint (e.g. an external picture URL).
   *
   * @since 1.0.0
   *
   * @return array<int, string>|null map of size (px) to URL
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Avatar URLs by size in pixels. Null when no avatar is set or the avatar is hosted externally.',
    readable: true,
    writable: false,
    required: false,
    identifier: false,
    example: [
      '256' => 'https://sso.example.com/api/users/550e8400-e29b-41d4-a716-446655440000/avatar/256.webp',
      '128' => 'https://sso.example.com/api/users/550e8400-e29b-41d4-a716-446655440000/avatar/128.webp',
      '64' => 'https://sso.example.com/api/users/550e8400-e29b-41d4-a716-446655440000/avatar/64.webp',
      '32' => 'https://sso.example.com/api/users/550e8400-e29b-41d4-a716-446655440000/avatar/32.webp',
    ],
    openapiContext: [
      'type' => 'object',
      'additionalProperties' => ['type' => 'string', 'format' => 'uri'],
      'nullable' => true,
      'readOnly' => true,
    ],
  )]
  public function getAvatarUrls(): ?array
  {
    return AvatarUrls::fromAvatarUrl($this->avatarUrl);
  }
  // #endregion
}
