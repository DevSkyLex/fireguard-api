<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationRoleInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationRoleInput
{
  // #region Properties
  /**
   * Property name.
   *
   * New role name, when renaming. System roles cannot be renamed —
   * {@see \Organization\Application\UseCase\Command\Organization\UpdateOrganizationRole\UpdateOrganizationRoleHandler}
   * refuses the whole update for a system role regardless of which fields
   * change. `null` leaves the current name unchanged.
   *
   * @since 1.1.0
   */
  #[Assert\Regex(
    pattern: '/^[a-z0-9_]{3,50}$/',
    message: 'Role name must be 3-50 chars, lowercase alphanumeric or underscore.',
  )]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'New role name, when renaming (system roles cannot be renamed)', required: false, example: 'site_manager')]
  public ?string $name = null;

  /**
   * @var list<string>
   */
  #[Assert\NotBlank(message: 'At least one permission is required.')]
  #[Assert\All([
    new Assert\Type(type: 'string'),
    new Assert\NotBlank(message: 'Permission cannot be blank.'),
  ])]
  /**
   * Property permissions.
   *
   * @since 1.0.0
   */
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Role permissions', required: true, example: ['organization.read', 'organization.members.read'])]
  public array $permissions = [];

  /**
   * Property description.
   *
   * @since 1.0.0
   */
  #[Assert\Length(max: 500, maxMessage: 'Role description cannot exceed 500 characters.')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Role description', required: false, example: 'Can inspect equipment')]
  public ?string $description = null;
  // #endregion
}
