<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\User;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use User\Presentation\Api\Serialization\UserSerializationGroup;

/**
 * DTO CurrentUserProfileOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CurrentUserProfileOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * The user ID.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: false)]
  public ?string $id = null;

  /**
   * Property username.
   *
   * The username.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $username = null;

  /**
   * Property email.
   *
   * The user email.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $email = null;

  /**
   * Property firstName.
   *
   * The first name.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * The last name.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastName = null;

  /**
   * Property avatarUrl.
   *
   * The avatar URL.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $avatarUrl = null;

  /**
   * Property status.
   *
   * The user status.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $status = null;

  /**
   * Property emailVerified.
   *
   * Whether the email is verified.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $emailVerified = false;

  /**
   * Property tenantId.
   *
   * The tenant ID.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $tenantId = null;

  /**
   * Property createdAt.
   *
   * The creation timestamp.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $createdAt = null;

  /**
   * Property lastLoginAt.
   *
   * The last login timestamp.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastLoginAt = null;

  /**
   * Property locale.
   *
   * The preferred display language. "system" means the user follows their
   * browser language.
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Preferred display language. "system" follows the browser language.',
    readable: true,
    writable: false,
    example: 'fr',
    openapiContext: [
      'type' => 'string',
      'enum' => ['system', 'en', 'fr', 'es'],
      'readOnly' => true,
    ],
  )]
  public string $locale = 'system';

  /**
   * @var list<string>
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Resolved global role names for the authenticated user.',
    readable: true,
    writable: false,
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'readOnly' => true,
    ],
  )]
  public array $roles = [];

  /**
   * @var list<string>
   */
  #[Groups(groups: [UserSerializationGroup::READ])]
  #[ApiProperty(
    description: 'Resolved global permission names for the authenticated user.',
    readable: true,
    writable: false,
    openapiContext: [
      'type' => 'array',
      'items' => ['type' => 'string'],
      'readOnly' => true,
    ],
  )]
  public array $permissions = [];
  // #endregion

  // #region Methods
  /**
   * Method getAvatarUrls.
   *
   * Derives the per-size avatar URLs from the canonical avatar URL.
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
