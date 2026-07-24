<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO OrganizationMemberOutput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMemberOutput
{
  // #region Properties
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $id = '';

  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $organizationId = '';

  /**
   * Property userId.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $userId = '';

  /**
   * Property email.
   *
   * @since 1.2.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $email = null;

  /**
   * Property firstName.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastName = null;

  /**
   * Property displayName.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $displayName = '';

  /**
   * Property avatarUrl.
   *
   * @since 1.1.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?string $avatarUrl = null;

  /**
   * Property isActive.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isActive = true;

  /**
   * Property isOwner.
   *
   * Whether this member is the organization owner (`userId` matches
   * `Organization::ownerUserId`).
   *
   * @since 1.4.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public bool $isOwner = false;

  /**
   * Property joinedAt.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $joinedAt = '';

  /**
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $roleIds = [];

  /**
   * @var list<string>
   */
  #[Groups([OrganizationSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public array $roleNames = [];
  // #endregion
}
